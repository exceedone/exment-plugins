<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomValueModelScope;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\ErrorCode;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\RelationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exceedone\Exment\ColumnItems\CustomColumns\Editor;
use Exceedone\Exment\Model\Define;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * A deliberately small "read-only" HTTP surface that lets a customer
 * write their own list / edit UI on top of the existing Exment CRUD.
 *
 * All routes are registered by the plugin, so they live under
 * {admin_prefix}/plugins/{plugin uri}/lightapi and reuse the admin session
 * cookie (no Passport, no scope config). Endpoints:
 *
 *   GET  lightapi/table/{tableKey}/schema
 *        Column metadata: labels, types, options, default, required. The
 *        response is stable enough to drive a generated form.
 *
 *   GET  lightapi/data/{tableKey}
 *        Paginated list with column selection, per-column equality
 *        filter, text search, order-by. Optional label enrichment for
 *        select / user / relation columns.
 *
 *   GET  lightapi/data/{tableKey}/{id}
 *        Single row, always with labels resolved.
 *
 *   GET  lightapi/lookup/{tableKey}?q=&limit=
 *        Search-as-you-type helper for relation / autocomplete widgets.
 *
 *   GET    lightapi/data/{tableKey}/create
 *   GET    lightapi/data/{tableKey}/{id}/edit
 *   POST   lightapi/data/{tableKey}
 *   PATCH  lightapi/data/{tableKey}/{id}
 *   DELETE lightapi/data/{tableKey}/{id}
 *        Form metadata and the writes that go with it. Every write runs
 *        Exment's own validateValue and the plugin's saving event, so
 *        validation, workflow and notifications behave exactly as they do
 *        on the admin screens.
 */
class LightApiController
{
    /** Cap requested per_page so a bad client cannot pull the whole table. */
    protected const MAX_PER_PAGE = 200;
    protected const DEFAULT_PER_PAGE = 50;

    /**
     * readableProjects(), worked out once per request.
     *
     * @var array<int, int>|null
     */
    protected $readableProjectIds;

    /**
     * Absolute URL for one of this controller's endpoints.
     *
     * The endpoints are registered by the plugin, so they sit under the
     * plugin path - not at the admin root. Building the URLs by hand with
     * url('admin/lightapi/...') produced links that 404, and it also
     * assumed the admin prefix is literally "admin", which is
     * configurable. admin_url() gets both right, and the plugin path itself
     * comes from the plugin row, since an admin can change it.
     *
     * @param string $path relative to the lightapi base, no leading slash
     * @return string
     */
    protected function apiUrl(string $path = ''): string
    {
        $base = PortalDefinition::routeUri() . '/lightapi';
        if ($path !== '') {
            $base .= '/' . ltrim($path, '/');
        }

        return admin_url($base);
    }

    /**
     * GET lightapi/table/{tableKey}/schema
     */
    public function schema(Request $request, string $tableKey): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        return response()->json([
            'table' => [
                'name' => $table->table_name,
                'label' => $table->table_view_name,
            ],
            'columns' => $this->describeColumns($table, $this->rowScope($table, $request->query('parent_id'))),
        ]);
    }

    /**
     * GET lightapi/data/{tableKey}
     */
    public function list(Request $request, string $tableKey): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        // Issue links are read through PmPortalController::buildRelations(),
        // which already narrows them to the issues of the project on screen.
        // Nothing reads them through here, so enumerating the table from this
        // endpoint only ever served as a way around that - it answered with
        // every link in the space, including ones between two issues the
        // caller cannot open. If a client ever does need the list, filter it
        // on the two indexed id columns against the issue table's own scoped
        // query rather than opening this up again.
        if ($this->isIssueLinkTable($table)) {
            return $this->err(404, 'table_not_found');
        }

        $columns = $this->pickColumns($table, $request->query('columns'));
        $perPage = min(self::MAX_PER_PAGE, max(1, intval($request->query('per_page', self::DEFAULT_PER_PAGE))));
        $page = max(1, intval($request->query('page', 1)));

        $model = $table->getValueModel()->newQuery();
        $this->whereReadableProject($model, $table);
        $this->applyFilters($model, $table, (array) $request->query('filter', []));
        $this->applySearch($model, $table, $request->query('search'));
        $this->applyOrder($model, $table, $request->query('orderby'));

        $total = (clone $model)->count();
        $rows = $model->forPage($page, $perPage)->get();

        $withLabels = $request->boolean('with_labels', true);
        $data = $withLabels
            ? $this->transformRowsWithLabels($rows, $columns)
            : $rows->map(function ($r) use ($columns) { return $this->transformRow($r, $columns); });

        return response()->json([
            'meta' => [
                'table' => $table->table_name,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
            'rows' => $data,
        ]);
    }

    /**
     * GET lightapi/data/{tableKey}/{id}
     */
    public function find(Request $request, string $tableKey, $id): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        $row = $this->findRow($table, $id);
        if (!$row) { return $this->err(404, 'not_found'); }

        $access = $this->readable($table, $row);
        if ($access !== true) { return $this->err(403, 'forbidden', $access); }

        $columns = $this->pickColumns($table, $request->query('columns'));
        $out = $this->transformRowsWithLabels(collect([$row]), $columns)[0];

        return response()->json(['row' => $out]);
    }

    /**
     * GET lightapi/lookup/{tableKey}?q=&limit=
     *
     * Small helper for autocomplete widgets on the client. Matches the
     * table's label column with LIKE and returns { value, label } pairs.
     */
    public function lookup(Request $request, string $tableKey): JsonResponse
    {
        // Every table a portal column picks from is allowed here, and only
        // here: the user and organization pickers, and whatever a deployment
        // pointed a select_table column of its own at. A lookup answers with
        // an id and a label, nothing more.
        $table = $this->resolveTable($tableKey, $this->lookupTargets());
        if ($table instanceof JsonResponse) { return $table; }

        // Nothing picks a link row out of a list, and the table-level grant on
        // it is custom_value_edit_all, so the scope hands back every link in
        // the space. Measured, with this refusal taken out: the titles do not
        // come with them, because SelectTable prints (no permission) for a row
        // the caller cannot read. What does come back is which issue ids are
        // linked to which, and by what relation - still an answer about a
        // project they are not on. Refused outright because there is no
        // single row here to check against.
        if ($this->isIssueLinkTable($table)) {
            return $this->err(404, 'table_not_found');
        }

        $q = trim((string) $request->query('q', ''));
        $limit = min(50, max(1, intval($request->query('limit', 20))));

        $model = $this->orderedLookupQuery($table);
        $this->whereReadableProject($model, $table);
        if ($q !== '') {
            $this->applySearch($model, $table, $q);
        }

        // Without this the list answers with every project's copy: seven rows
        // called 未対応, one of which is the caller's, and nothing in the
        // label says which. Narrowed in the query rather than in the browser
        // because the limit is the database's - a client side filter would
        // throw away most of the twenty rows it was handed and show three.
        $owner = $this->scopeOwner($table);
        $parentId = $request->query('parent_id');
        if (isset($owner) && is_scalar($parentId) && ctype_digit(strval($parentId))) {
            $model->where('parent_type', $owner->table_name)
                ->where('parent_id', intval($parentId));
        }

        // The other way a list says which project it belongs to: a column of
        // its own naming the project, rather than hanging off it as a child.
        // Milestones and categories are written that way, so the relation
        // above finds nothing for them and the picker offered every project's
        // milestones - including the ones an issue here must never point at.
        $scopeName = $request->query('scope_column');
        $scopeColumn = is_scalar($scopeName) ? CustomColumn::getEloquent(strval($scopeName), $table) : null;
        if (isset($scopeColumn) && is_scalar($parentId) && ctype_digit(strval($parentId))) {
            $model->where($scopeColumn->getQueryKey(), strval($parentId));
        }

        // A row is never its own parent.
        $exclude = $request->query('exclude');
        $exclude = is_scalar($exclude) ? array_filter(explode(',', strval($exclude)), 'ctype_digit') : [];
        if ($exclude) {
            $model->whereNotIn('id', $exclude);
        }

        // Where a hierarchy is one level deep, a row that already has a parent
        // cannot be one. Named as a column rather than baked in, so the
        // endpoint stays a filter and the rule stays in PortalRules.
        $emptyName = $request->query('empty');
        $emptyColumn = is_scalar($emptyName) ? CustomColumn::getEloquent(strval($emptyName), $table) : null;
        if (isset($emptyColumn)) {
            $this->whereColumnEmpty($model, $emptyColumn);
        }

        $rows = $model->limit($limit)->get();

        $results = $rows->map(function ($r) {
            return ['value' => $r->id, 'label' => (string) $r->getLabel()];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Narrow to the rows holding nothing in one column.
     *
     * @param \Illuminate\Database\Eloquent\Builder $model
     * @param CustomColumn $column
     * @return void
     */
    protected function whereColumnEmpty($model, CustomColumn $column): void
    {
        // An indexed column is a real database column - cheaper to read and
        // cheaper to filter on than digging through the json. Guarded on the
        // flag rather than on the method: getIndexColumnName() adds the
        // column to the table when it is missing, so asking an unindexed
        // column for its name alters the schema as a side effect.
        $index = $column->index_enabled ? $column->getIndexColumnName() : null;
        if ($index) {
            $model->where(function ($w) use ($index) {
                $w->whereNull($index)->orWhere($index, '');
            });

            return;
        }

        // JSON_UNQUOTE answers SQL NULL for a key that was never written and
        // the four letter string "null" for one written as json null, so both
        // have to be named.
        $path = '$."' . $column->column_name . '"';
        $model->where(function ($w) use ($path) {
            $w->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(value, ?)) IS NULL', [$path])
                ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(value, ?)) IN ('', 'null')", [$path]);
        });
    }

    /**
     * The table's rows in the order a picker shows them.
     *
     * A master list carries an explicit sort of its own; honour it, or the
     * picker shuffles what the settings screen carefully ordered. Everything
     * else answers newest first - on a table too big to list whole, the recent
     * rows are the ones a picker is usually after.
     *
     * @param CustomTable $table
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function orderedLookupQuery(CustomTable $table)
    {
        $model = $table->getValueModel()->newQuery();

        if (CustomColumn::getEloquent('display_order', $table) !== null) {
            return $model
                ->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(value, '$.display_order')) AS UNSIGNED) asc")
                ->orderBy('id');
        }

        return $model->orderBy('id', 'desc');
    }

    /**
     * The table a row of $table hangs off, when there is one.
     *
     * Being a one to many child is what makes "this project's statuses" a
     * question the database can answer at all. A list shared by the whole
     * space - priority, resolution - has no such parent and is returned whole.
     *
     * @param CustomTable $table
     * @return CustomTable|null
     */
    protected function scopeOwner(CustomTable $table)
    {
        $relation = CustomRelation::getRelationByChild($table, RelationType::ONE_TO_MANY);

        return isset($relation) ? $relation->parent_custom_table : null;
    }

    /**
     * Where a row sits, for the benefit of the lists its own columns offer.
     *
     * @param CustomTable $table
     * @param mixed $parentId
     * @param mixed $exclude row id a self referencing column must not offer
     * @return array<string, string|null>
     */
    protected function rowScope(CustomTable $table, $parentId, $exclude = null): array
    {
        $owner = $this->scopeOwner($table);
        $parentId = is_scalar($parentId) ? strval($parentId) : '';

        return [
            'parent_type' => isset($owner) ? $owner->table_name : null,
            'parent_id' => ctype_digit($parentId) ? $parentId : null,
            'exclude' => is_nullorempty($exclude) ? null : strval($exclude),
        ];
    }

    /**
     * GET lightapi/data/{tableKey}/{id}/edit
     *
     * Everything a client needs to render its own edit form for one row
     * without loading the /admin/data/{table}/{id}/edit HTML page:
     *
     *   - "columns" is the same shape as /schema (labels, types,
     *     options, required, help, lookup URLs)
     *   - "row.value" is the current raw value dictionary
     *   - "row.labels" is the human-readable label per relation-ish
     *     column so a chip-picker can render pre-selected values
     *     without a second round-trip
     *   - "save" tells the caller where to PATCH the changed values
     *
     * Reuses the same permission check as find() (row->enableAccess),
     * and additionally requires row->enableEdit so we do not hand out
     * form data for rows the caller cannot actually save.
     */
    public function editForm(Request $request, string $tableKey, $id): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        $row = $this->findRow($table, $id);
        if (!$row) { return $this->err(404, 'not_found'); }

        // Same gate as find(): this answers with the two issue ids *and* their
        // titles, so a link between two issues of another project would hand
        // them over just as readily.
        $access = $this->readable($table, $row);
        if ($access !== true) { return $this->err(403, 'forbidden', $access); }
        $canEdit = $this->isIssueLinkTable($table)
            ? (PortalRules::linkRefusal(PortalDefinition::config(), (array) $row->value, (array) $row->value) === null ? true : ErrorCode::PERMISSION_DENY())
            : PortalDefinition::enableEdit($row, false);

        $columns = $this->describeColumns($table, $this->rowScope($table, $row->parent_id, $row->id));
        $allCols = [];
        foreach ($table->custom_columns as $c) { $allCols[$c->column_name] = $c; }

        $withLabels = $this->transformRowsWithLabels(collect([$row]), $allCols)[0] ?? [];
        $labels = [];
        foreach ($withLabels as $k => $v) {
            if (str_ends_with($k, '_label')) { $labels[substr($k, 0, -6)] = $v; }
        }

        $value = is_array($row->value) ? $row->value : [];
        foreach ($allCols as $name => $column) {
            if ($column->column_type === ColumnType::EDITOR && isset($value[$name])) {
                $value[$name] = Editor::replaceImgUrl($value[$name]);
            }
            // A date and time opens in the form as the user reads it;
            // persistRow turns what is typed back into the time of the site.
            if ($column->column_type === ColumnType::DATETIME && isset($value[$name])) {
                $value[$name] = PortalTime::toUser($value[$name]);
            }
        }

        // Resolve creator/updater names for the header meta strip.
        // Batched: at most ONE whereIn query, no matter whether the two
        // ids are equal, different, or one is null. Two Eloquent finds
        // were the third biggest cost per open (~3-5ms) before this.
        $creator = null; $updater = null;
        try {
            $userTable = CustomTable::getEloquent(SystemTableName::USER);
            if ($userTable) {
                $ids = array_values(array_unique(array_filter([
                    $row->created_user_id,
                    $row->updated_user_id,
                ])));
                if ($ids) {
                    $users = $userTable->getValueModel()->whereIn('id', $ids)->get();
                    $userLabels = [];
                    foreach ($users as $u) { $userLabels[$u->id] = (string) $u->getLabel(); }
                    if ($row->created_user_id && isset($userLabels[$row->created_user_id])) {
                        $creator = $userLabels[$row->created_user_id];
                    }
                    if ($row->updated_user_id && isset($userLabels[$row->updated_user_id])) {
                        $updater = $userLabels[$row->updated_user_id];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: header meta is decorative.
        }

        return response()->json([
            'table' => ['name' => $table->table_name, 'label' => $table->table_view_name],
            'columns' => $columns,
            'row' => [
                'id' => $row->id,
                'value' => $value,
                'labels' => $labels,
                // As stored: updated_at is the version a save quotes back.
                'created_at' => $row->created_at ? (string) $row->created_at : null,
                'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
                // As shown, in the time zone of the user.
                'created_at_shown' => PortalTime::format($row->created_at, 'Y-m-d H:i:s') ?: null,
                'updated_at_shown' => PortalTime::format($row->updated_at, 'Y-m-d H:i:s') ?: null,
                'created_by' => $creator,
                'updated_by' => $updater,
                'label' => (string) $row->getLabel(),
            ],
            'permissions' => [
                'can_edit' => $canEdit === true,
                // A plain refusal is left to the editor's own wording; any
                // other reason (a workflow lock, say) is worth spelling out.
                'edit_denied' => $canEdit === true || strval($canEdit) === ErrorCode::PERMISSION_DENY
                    ? null : $this->reasonText($canEdit),
            ],
            'save' => [
                'method' => 'PATCH',
                'url' => $this->apiUrl("data/{$table->table_name}/{$row->id}"),
            ],
        ]);
    }

    /**
     * GET lightapi/data/{tableKey}/create
     *
     * Same shape as {id}/edit but for a fresh row: the returned "row" has
     * id=null, value={} pre-filled with each column's declared default,
     * labels={}, and save.method=POST / save.url points at the sibling
     * create endpoint. Permission uses table->enableCreate(true) so
     * the same workflow / form-action guard as the admin create form
     * applies.
     */
    public function createForm(Request $request, string $tableKey): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        $canCreate = $table->enableCreate(true);
        if ($canCreate !== true) { return $this->err(403, 'forbidden', $canCreate); }

        $scope = $this->rowScope($table, $request->query('parent_id'));
        $columns = $this->describeColumns($table, $scope);
        $value = [];
        foreach ($columns as $c) {
            if (($c['name'] ?? null) === 'id') { continue; }
            if (($c['name'] ?? null) === 'created_at' || ($c['name'] ?? null) === 'updated_at') { continue; }
            $d = $c['default'] ?? null;
            if ($d === null || $d === '') {
                $d = $this->scopedDefault($c, $table, $scope);
            }
            if ($d !== null && $d !== '') { $value[$c['name']] = $d; }
        }
        $prefill = $this->prefillValues($request, $columns);
        $value = array_merge($value, $prefill);

        // The schema does not depend on the calling user, only on the
        // table definition. Ask the browser to keep it for 60s so a
        // second click on the create button in the same tab skips the whole
        // Laravel bootstrap round-trip. NED also memoises client-side.
        return response()->json([
            'table' => ['name' => $table->table_name, 'label' => $table->table_view_name],
            'columns' => $columns,
            'row' => [
                'id' => null,
                'value' => $value,
                'labels' => $this->defaultLabels($columns, $value),
                'created_at' => null,
                'updated_at' => null,
                'label' => '',
            ],
            'templates' => $this->issueTypeTemplates($table, $scope),
            'permissions' => [
                'can_edit' => true,
                'edit_denied' => null,
            ],
            'save' => [
                'method' => 'POST',
                'url' => $this->apiUrl("data/{$table->table_name}"),
            ],
        // A plain create form is the same for everybody in this tab, so it is
        // worth keeping; a prefilled one describes one row and must not be
        // handed to the next caller that asks for an empty form.
        ])->header('Cache-Control', empty($prefill) ? 'private, max-age=60' : 'no-store');
    }

    /**
     * Values the caller wants the create form to open on.
     *
     * "Add a child issue" and "duplicate this issue" are the ordinary create
     * form with some answers already filled in. Resolving them here rather
     * than in the browser means a select_table field arrives with its label
     * attached, exactly like a default does - the picker has nothing left to
     * look up, and the form is "clean" from the first paint, so closing it
     * without typing does not ask about unsaved work.
     *
     * Only columns this form is already showing are accepted: a caller
     * cannot reach past the form into a column the table does not expose,
     * nor write one the form itself renders read-only.
     *
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    protected function prefillValues(Request $request, array $columns): array
    {
        $raw = $request->query('prefill');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw) || empty($raw)) {
            return [];
        }

        $allowed = [];
        foreach ($columns as $c) {
            $name = strval(array_get($c, 'name', ''));
            if ($name !== '' && $name !== 'id' && empty($c['readonly'])) {
                $allowed[$name] = true;
            }
        }

        $out = [];
        foreach ($raw as $name => $v) {
            $name = strval($name);
            if (!isset($allowed[$name]) || $v === null || $v === '' || (is_array($v) && empty($v))) {
                continue;
            }
            $out[$name] = $v;
        }

        return $out;
    }

    /**
     * Pick the default for a select_table column whose rows belong to a
     * project.
     *
     * The column itself cannot carry one: status and issue type are
     * per-project lists, so no single row id is right for everyone. Leaving
     * the field empty is not an option either - an issue with no status
     * never lands in a board column - so the project's own first row is
     * used, which is what Backlog does. Order is the picker's, so the "not
     * started" status and the "task" type win.
     *
     * @param array<string, mixed> $column
     * @param CustomTable $table the table the new row goes in
     * @param array<string, string|null> $scope
     * @return string|null
     */
    protected function scopedDefault(array $column, CustomTable $table, array $scope)
    {
        $parentType = array_get($scope, 'parent_type');
        $parentId = array_get($scope, 'parent_id');
        if (($column['raw_type'] ?? null) !== 'select_table'
            || is_nullorempty($parentType) || is_nullorempty($parentId)) {
            return null;
        }

        $target = CustomTable::getEloquent($column['target_table'] ?? null);
        if (!isset($target)) {
            return null;
        }

        // A column pointing back at its own table links two rows - a parent
        // issue - it is not a list of settings to start from. Filling it in
        // hangs every new issue off whichever row happened to come first.
        if ($target->id == $table->id) {
            return null;
        }

        // Only a list owned by the same project as the new row has a copy
        // that belongs to it; a space wide master is nobody's in particular.
        $owner = $this->scopeOwner($target);
        if (!isset($owner) || $owner->table_name !== $parentType) {
            return null;
        }

        $row = $this->orderedLookupQuery($target)
            ->where('parent_type', $parentType)
            ->where('parent_id', $parentId)
            ->first();

        return isset($row) ? strval($row->id) : null;
    }

    /**
     * Readable names for the defaults a create form hands back.
     *
     * The picker paints a chip per value it is given, labelled with what it
     * was told. A create form has no row to read labels off, so without this
     * a default arrives as a bare row id - "166" where the user expects
     * 未対応 - which reads as junk, gets deleted, and leaves an issue with
     * no status that no board column can hold.
     *
     * @param array<int, array<string, mixed>> $columns
     * @param array<string, mixed> $value
     * @return array<string, string>
     */
    protected function defaultLabels(array $columns, array $value): array
    {
        $labels = [];

        foreach ($columns as $c) {
            $name = $c['name'] ?? null;
            $raw = isset($name) ? ($value[$name] ?? null) : null;
            $target = $this->referenceTable($c);
            if ($raw === null || $raw === '' || !isset($target)) {
                continue;
            }

            // One value or many. A multi-value column hands the picker one
            // chip per id, and the picker splits this string on the same
            // separator the edit form's labels arrive with.
            $ids = [];
            foreach ((is_array($raw) ? $raw : [$raw]) as $one) {
                if (is_scalar($one) && ctype_digit(strval($one))) {
                    $ids[] = strval($one);
                }
            }
            if (empty($ids)) {
                continue;
            }

            $named = [];
            $found = [];
            foreach ($target->getValueModel()->whereIn('id', $ids)->get() as $row) {
                $found[strval($row->id)] = strval($row->getLabel());
            }
            foreach ($ids as $id) {
                if (isset($found[$id])) {
                    $named[] = $found[$id];
                }
            }
            if (!empty($named)) {
                $labels[$name] = implode('、', $named);
            }
        }

        return $labels;
    }

    /**
     * The table a column points at, for the column types that hold a row id
     * rather than a word of their own.
     *
     * @param array<string, mixed> $column one entry of describeColumns()
     * @return CustomTable|null
     */
    protected function referenceTable(array $column)
    {
        $type = $column['raw_type'] ?? null;
        if ($type === 'select_table') {
            return CustomTable::getEloquent($column['target_table'] ?? null);
        }
        if ($type === 'user') {
            return CustomTable::getEloquent(SystemTableName::USER);
        }
        if ($type === 'organization') {
            return CustomTable::getEloquent(SystemTableName::ORGANIZATION);
        }

        return null;
    }

    /**
     * The column a list uses to name the record that owns it, for a list
     * that names its owner rather than hanging off it.
     *
     * @param CustomTable $target the list being offered
     * @param string $ownerTableName table_name of the owning record
     * @return string|null column name, or null where there is no such column
     */
    protected function ownerColumn(CustomTable $target, string $ownerTableName): ?string
    {
        foreach ($target->custom_columns as $c) {
            if ($c->column_type !== 'select_table') {
                continue;
            }

            $opts = $c->options ?? [];
            if (empty($opts['select_target_table'])) {
                continue;
            }

            // getEloquent is process-cached, so this costs nothing the second
            // time the same table is asked for.
            $owner = CustomTable::getEloquent($opts['select_target_table']);
            if (isset($owner) && $owner->table_name === $ownerTableName) {
                return $c->column_name;
            }
        }

        return null;
    }

    /**
     * The subject and description each issue type wants a new issue to
     * start from - Backlog's per-type template, filled in the moment the
     * type is picked.
     *
     * Handed over with the create form so switching the type costs no
     * request. Only the issue table carries these, only the scope's own
     * types are sent, and a type with nothing to say is left out; a null
     * hands the client nothing to wire up at all.
     *
     * @param CustomTable $table
     * @param array<string, string|null> $scope parent scope of the new row
     * @return array{column: string, rows: array<string, array<string, string>>}|null
     */
    protected function issueTypeTemplates(CustomTable $table, array $scope): ?array
    {
        $def = PortalDefinition::config();
        if ($table->table_name !== strval(array_get($def, 'issue_table'))) {
            return null;
        }
        $type_column = strval(array_get($def, 'issue.type'));
        $target = CustomTable::getEloquent(array_get($def, 'issue_type_table'));
        $subject_col = strval(array_get($def, 'issue_type_row.template_subject'));
        $description_col = strval(array_get($def, 'issue_type_row.template_description'));
        if ($type_column === '' || !isset($target) || $subject_col === '' || $description_col === '') {
            return null;
        }

        // An install that has not run the schema upgrade has no template
        // columns yet; asking would only ever answer with empty strings.
        if (CustomColumn::getEloquent($subject_col, $target) === null) {
            return null;
        }

        $query = $this->orderedLookupQuery($target);
        $parentType = array_get($scope, 'parent_type');
        $parentId = array_get($scope, 'parent_id');
        if (!is_nullorempty($parentType) && !is_nullorempty($parentId)) {
            $query->where('parent_type', strval($parentType))
                ->where('parent_id', strval($parentId));
        }

        $rows = [];
        foreach ($query->take(100)->get() as $row) {
            $subject = strval(array_get($row->value, $subject_col, ''));
            $description = strval(array_get($row->value, $description_col, ''));
            if ($subject === '' && $description === '') {
                continue;
            }
            $rows[strval($row->id)] = ['s' => $subject, 'd' => $description];
        }

        return empty($rows) ? null : ['column' => $type_column, 'rows' => $rows];
    }

    /**
     * Key validation messages by column name.
     *
     * Two validators guard a save and they disagree on naming. Exment's own
     * rules report against the payload path, so a bad project key arrives as
     * "value.project_code", while PortalRules reports the plain column name.
     * The client looks a field up by its column name alone, so a prefixed key
     * matches nothing: the input is never marked and the message is dropped,
     * leaving only a generic error toast. Normalise here, at the boundary, so
     * every consumer sees one shape.
     *
     * @return array<string, array<int, string>>
     */
    protected function validationFields(ValidationException $e): array
    {
        $out = [];
        foreach ($e->validator->errors()->toArray() as $key => $messages) {
            $name = preg_replace('/^value\./', '', strval($key));
            // A multi-value column reports per index ("category.0"); the form
            // has one input for the column, so fold the indexes together.
            $name = explode('.', $name)[0];
            $out[$name] = array_merge($out[$name] ?? [], (array) $messages);
        }

        return $out;
    }

    /**
     * POST lightapi/data/{tableKey}
     *
     * Accepts a JSON body {"value": {...}} and creates a new row. Runs
     * Exment's validateValue so the same required / max_length / regex
     * checks that guard the admin create form apply; validation failures
     * come back as HTTP 422 with a per-field message map. Returns the
     * saved row (with resolved labels) and its new id on success.
     *
     * A row that belongs to another row through Exment's parent relation
     * (an issue inside a project, a wiki page inside a project) carries
     * that link in parent_id / parent_type rather than in a custom
     * column, so both are accepted at the top level of the body. Without
     * them a created issue has no project and is invisible to every
     * project-scoped query.
     */
    public function create(Request $request, string $tableKey): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        // A link is the portal's to judge (guardIssueLink below): the role
        // grants on the link table were taken away so that Exment's own
        // screens stop listing and deleting every link in the space.
        if (!$this->isIssueLinkTable($table)) {
            $canCreate = $table->enableCreate(true);
            if ($canCreate !== true) { return $this->err(403, 'forbidden', $canCreate); }
        }

        $input = $request->input('value');
        if (!is_array($input)) { $input = []; }
        $filtered = array_intersect_key($input, $this->writableColumns($table));

        $parent = $this->resolveParent($request, $table);
        if ($parent instanceof JsonResponse) { return $parent; }
        if (!$parent && $this->livesInProject($table)) { return $this->err(400, 'parent_required'); }

        $row = $table->getValueModel();
        if ($parent) {
            $row->parent_id = $parent['id'];
            $row->parent_type = $parent['type'];
        }
        $filtered = $this->acceptLegacyValues($table, $filtered, $row->parent_id);
        if ($deny = $this->guardIssueLink($table, $filtered)) { return $deny; }
        // "Also by e-mail" on a new issue: the bells the save rings are the
        // rows past this mark, and their people get the mail.
        $mail = $this->wantsIssueMail($request, $table);
        // The mail and Teams of a new issue (ISS02863): the people of the bells, as each chose
        $notices = $table->table_name === strval(array_get(PortalDefinition::config(), 'issue_table'));
        $bellMark = $notices ? intval(NotifyNavbar::withoutGlobalScopes()->max('id')) : 0;
        try {
            $images = $this->persistRow($table, $row, $filtered);
        } catch (ValidationException $e) {
            return $this->invalid($e);
        } catch (\Throwable $e) {
            return $this->failed('save_failed', $e);
        }

        $row->refresh();
        $this->markWriterRead($table, $row);
        if ($notices) {
            $this->mailNewIssue($table, $row, $bellMark, $mail);
        }
        $columns = $this->pickColumns($table, null);
        $out = $this->transformRowsWithLabels(collect([$row]), $columns)[0] ?? [];
        return response()->json(['ok' => true, 'id' => $row->id, 'row' => $out, 'image_urls' => $images]);
    }

    /**
     * Whether a new row is an issue whose writer ticked "also send by e-mail".
     */
    protected function wantsIssueMail(Request $request, CustomTable $table): bool
    {
        if ($table->table_name !== strval(array_get(PortalDefinition::config(), 'issue_table'))) {
            return false;
        }
        $flag = $request->input('notify_mail');

        return is_scalar($flag) && in_array(strval($flag), ['1', 'true', 'on'], true);
    }

    /**
     * Whoever saved an issue has seen it as it now stands: their read receipt
     * moves on, so the list does not show them their own edit as unread news
     * and the issue does not list them as having read an older version.
     *
     * @param mixed $row
     */
    protected function markWriterRead(CustomTable $table, $row): void
    {
        if ($table->table_name !== strval(array_get(PortalDefinition::config(), 'issue_table'))) {
            return;
        }
        try {
            Star::markRead($table->table_name, $row->id, \Exment::getUserId());
        } catch (\Throwable $e) {
            Log::warning('pm_portal read mark for issue ' . $row->id . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * Mail the people a new issue's bells went to (PortalMail), after the
     * answer is out. A failure here is logged: the issue is saved.
     *
     * @param mixed $row
     */
    protected function mailNewIssue(CustomTable $table, $row, int $bellMark, bool $mail = true): void
    {
        try {
            $def = PortalDefinition::config();
            $key = $this->issueCardKey($row);
            $subject = strval(array_get($row->value, array_get($def['issue'], 'subject'), ''));
            $description = strval(array_get($row->value, array_get($def['issue'], 'description'), ''));
            // The description is editor HTML; the mail carries its text.
            $description = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $description) ?? ''), ENT_QUOTES, 'UTF-8'));
            $body = strval(PortalDefinition::trans('mail_new_issue')) . ($description !== '' ? "\n\n" . $description : '');
            // The project by its code and the issue by its key, like the
            // other mails (PmPortalController::issueUrl(), ISS00440).
            $url = PortalDefinition::projectUrlById($row->parent_id) . '#issue-' . rawurlencode(PortalDefinition::issueAnchor($row));
            PortalTeams::afterResponse($table->table_name, $row, $bellMark, \Exment::getUserId(), 'new', $key, $subject, $description !== '' ? [mb_substr($description, 0, 500)] : [], $url, $mail);
        } catch (\Throwable $e) {
            Log::error('pm_portal mail for new issue ' . $row->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * PATCH lightapi/data/{tableKey}/{id}
     *
     * Accepts a JSON body {"value": {"col_a": ..., "col_b": ...}} and
     * updates only the columns present. Undeclared columns keep their
     * existing values. Runs Exment's own validateValue so the same
     * required / max_length / regex / date rules that guard the admin
     * form apply here too; validation failures come back as HTTP 422
     * with a per-field message map so the caller can highlight fields.
     *
     * Permission uses row->enableEdit(true) so the CRUD form-action
     * check (workflow lock, share, table permission) is honoured.
     */
    public function update(Request $request, string $tableKey, $id): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        $row = $this->findRow($table, $id);
        if (!$row) { return $this->err(404, 'not_found'); }

        // A link is judged on what it joins, in guardIssueLink() below.
        if (!$this->isIssueLinkTable($table)) {
            $canEdit = PortalDefinition::enableEdit($row, true);
            if ($canEdit !== true) { return $this->err(403, 'forbidden', $canEdit); }
        }

        // A form is a photograph of a row taken when it opened. Saving it back
        // writes every changed field from that photograph, so a colleague who
        // saved in between has their work quietly undone - and nothing on
        // either screen ever says so. A caller that tells us which version it
        // read is told when that version is no longer the current one; a
        // caller that says nothing keeps the old behaviour.
        $since = $request->input('updated_at');
        if (!is_nullorempty($since) && $row->updated_at
            && strval($row->updated_at) !== strval($since)) {
            return PortalDefinition::jsonError(409, 'stale', [
                'updated_at' => (string) $row->updated_at,
                'updated_at_shown' => PortalTime::format($row->updated_at, 'Y-m-d H:i:s'),
                'updated_by' => $this->rowUpdaterLabel($row),
            ]);
        }

        $input = $request->input('value');
        if (!is_array($input)) { $input = []; }
        $filtered = array_intersect_key($input, $this->writableColumns($table));

        $parent = $this->resolveParent($request, $table);
        if ($parent instanceof JsonResponse) { return $parent; }
        if ($parent && !is_nullorempty($row->parent_id)) {
            // A row stays in the project it was filed in. Moving an issue
            // carries the old project's statuses, types and milestones along
            // and strands its sub-issues; the portal never sends a move, and
            // PortalRules refuses one on every other path too. The row's own
            // parent sent back unchanged is no move at all.
            if (intval($row->parent_id) !== $parent['id'] || strval($row->parent_type) !== $parent['type']) {
                return $this->err(400, 'parent_locked');
            }
            $parent = null;
        }
        if (!$filtered && !$parent) { return $this->err(400, 'no_known_columns'); }

        if ($parent) {
            $row->parent_id = $parent['id'];
            $row->parent_type = $parent['type'];
        }
        $filtered = $this->acceptLegacyValues($table, $filtered, $row->parent_id);
        if ($deny = $this->guardIssueLink($table, $filtered, $row)) { return $deny; }
        try {
            $images = $this->persistRow($table, $row, $filtered);
        } catch (ValidationException $e) {
            return $this->invalid($e);
        } catch (\Throwable $e) {
            return $this->failed('save_failed', $e);
        }

        $row->refresh();
        $this->markWriterRead($table, $row);
        $columns = $this->pickColumns($table, null);
        $out = $this->transformRowsWithLabels(collect([$row]), $columns)[0] ?? [];

        // Handed back so a second save from the same open form carries the
        // version this one produced rather than the one it opened on.
        return response()->json([
            'ok' => true,
            'row' => $out,
            'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
            // The same moment as the user reads it (updated_at is the version).
            'updated_at_shown' => PortalTime::format($row->updated_at, 'Y-m-d H:i:s') ?: null,
            'image_urls' => $images,
        ]);
    }

    protected function persistRow(CustomTable $table, $row, array $value): array
    {
        $editors = [];
        $before = [];
        // A date and time the user typed is in the user's time zone; the
        // database keeps the time of the site. (A date alone, and a time
        // alone, are kept as typed.)
        if (PortalTime::differs()) {
            foreach ($table->custom_columns_cache as $column) {
                if ($column->column_type === ColumnType::DATETIME && isset($value[$column->column_name])) {
                    $value[$column->column_name] = PortalTime::fromUser($value[$column->column_name]);
                }
            }
        }
        foreach ($table->custom_columns_cache as $column) {
            if ($column->column_type !== ColumnType::EDITOR
                || !isset($value[$column->column_name]) || !is_string($value[$column->column_name])
                || !str_contains($value[$column->column_name], admin_urls('tmpfiles'))) {
                continue;
            }
            $item = $column->column_item;
            if ($item instanceof Editor) {
                $editors[$column->column_name] = $item;
                $before += $item->getPastedImageFiles($row);
            }
        }
        if (!$editors) {
            $row->setValueStrictly($value);
            $row->save();
            return [];
        }

        try {
            $row->getConnection()->transaction(function () use ($row, $value) {
                $row->setValueStrictly($value);
                $row->save();
            });
        } catch (\Throwable $error) {
            foreach ($editors as $item) {
                foreach ($item->getPastedImageFiles($row) as $uuid => $image) {
                    if (isset($before[$uuid])) { continue; }
                    try {
                        $temp = Storage::disk(Define::DISKNAME_TEMP_UPLOAD);
                        $uploads = Storage::disk(config('admin.upload.disk'));
                        if (!$temp->exists($image['temp_name'])
                            && !$temp->put($image['temp_name'], $uploads->get($image['file']->path))) {
                            throw new \RuntimeException('Could not restore a rolled-back clipboard image.');
                        }
                        if (!$uploads->delete($image['file']->path)) {
                            throw new \RuntimeException('Could not remove a rolled-back clipboard image.');
                        }
                    } catch (\Throwable $cleanupError) {
                        Log::error('PMPortal clipboard image rollback cleanup failed.', ['exception' => $cleanupError]);
                    }
                }
            }
            throw $error;
        }
        $urls = [];
        foreach ($editors as $name => $item) { $urls[$name] = $item->getPastedImageUrls($row); }
        return $urls;
    }

    /**
     * Who saved a row last, for the message that refuses to write over them.
     *
     * Decorative: a name that cannot be resolved leaves the message saying
     * only when the row moved, which is still the useful half.
     *
     * @param mixed $row
     * @return string|null
     */
    protected function rowUpdaterLabel($row)
    {
        if (is_nullorempty($row->updated_user_id)) { return null; }
        try {
            $userTable = CustomTable::getEloquent(SystemTableName::USER);
            if (!$userTable) { return null; }
            $user = $userTable->getValueModel()->find($row->updated_user_id);

            return $user ? (string) $user->getLabel() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Let a caller keep using the words a column accepted before the schema
     * upgrade turned it into a table pointer.
     *
     * Only word-shaped values on a select_table column are touched, and only
     * when the upgrade recorded a translation for them; anything else is passed
     * through untouched so a genuinely wrong value still fails validation
     * rather than being quietly swallowed.
     *
     * @param CustomTable $table
     * @param array<string, mixed> $values
     * @param string|int|null $parent_id
     * @return array<string, mixed>
     */
    protected function acceptLegacyValues(CustomTable $table, array $values, $parent_id = null): array
    {
        foreach ($table->custom_columns as $column) {
            $name = $column->column_name;
            if ($column->column_type != ColumnType::SELECT_TABLE || !array_key_exists($name, $values)) {
                continue;
            }

            $value = $values[$name];
            if (!is_string($value) || $value === '' || ctype_digit($value)) {
                continue;
            }

            $id = PortalUpgrade::legacyValue($name, $value, $parent_id);
            if (isset($id)) {
                $values[$name] = $id;
            }
        }

        return $values;
    }

    /**
     * DELETE lightapi/data/{tableKey}/{id}
     *
     * Deletes a single row through the model, so Exment's own delete
     * hooks (relations, documents, revisions) run exactly as they do on
     * the admin screen. The caller must be allowed to edit the row, not
     * merely see it.
     *
     * A row still referenced by another table is refused rather than
     * cascaded: silently blanking a status on fifty issues because
     * somebody tidied a settings list is not a recoverable mistake.
     */
    public function destroy(Request $request, string $tableKey, $id): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        $row = $this->findRow($table, $id);
        if (!$row) { return $this->err(404, 'not_found'); }

        if ($this->isIssueLinkTable($table)) {
            if ($deny = $this->guardIssueLink($table, null, $row)) { return $deny; }
        } else {
            $canEdit = PortalDefinition::enableDelete($row, true);
            if ($canEdit !== true) { return $this->err(403, 'forbidden', $canEdit); }
        }

        $owned = $this->ownedChildTables($table);
        $used = $this->countReferences($table, $row, $owned);
        if ($used > 0) { return $this->err(409, 'in_use', strval($used)); }

        try {
            // One unit of work. Without it a failure on the project row
            // itself leaves the status and issue-type masters already
            // deleted, so the project survives with nothing to put in a
            // status field - a state no retry and no screen can repair.
            \DB::transaction(function () use ($table, $row, $owned) {
                $this->deleteOwnedChildren($table, $row, $owned);
                // Stars and watches are keyed by (target_type, target_id)
                // and nothing joins them back to the row, so a delete that
                // leaves them behind leaves a subscription to a record
                // nobody can open, and a count that the id's next owner
                // would inherit.
                Star::purge($table->table_name, $row->id);
                $row->delete();
            });
        } catch (\Throwable $e) {
            return $this->failed('delete_failed', $e);
        }

        return response()->json(['ok' => true, 'id' => intval($id)]);
    }

    /**
     * POST lightapi/reorder/{tableKey}
     *
     * Body {"ids": [3, 1, 2]} - the rows of one master list in the order the
     * user just dragged them into. display_order is written as 10, 20, 30...
     * so a later insert-between still has room. A row the caller may not
     * edit refuses the whole request: a half-applied order would quietly
     * shuffle a list two people are looking at differently.
     */
    public function reorder(Request $request, string $tableKey): JsonResponse
    {
        $table = $this->resolveTable($tableKey);
        if ($table instanceof JsonResponse) { return $table; }

        if (CustomColumn::getEloquent('display_order', $table) === null) {
            return $this->err(400, 'no_order_column');
        }

        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) { return $this->err(400, 'no_ids'); }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) > 500) { return $this->err(400, 'too_many'); }

        $rows = $table->getValueModel()->newQuery()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $rowId) {
            $row = $rows->get($rowId);
            if (!$row) { return $this->err(404, 'not_found', strval($rowId)); }
            $canEdit = PortalDefinition::enableEdit($row, true);
            if ($canEdit !== true) { return $this->err(403, 'forbidden', $canEdit); }
        }

        $changed = 0;
        try {
            foreach ($ids as $i => $rowId) {
                $row = $rows->get($rowId);
                $order = strval(($i + 1) * 10);
                if (strval(array_get($row->value, 'display_order')) === $order) { continue; }
                $row->saved_notify(false);
                $row->setValue(['display_order' => $order]);
                $row->save();
                $changed++;
            }
        } catch (\Throwable $e) {
            return $this->failed('save_failed', $e);
        }

        return response()->json(['ok' => true, 'changed' => $changed]);
    }

    /**
     * GET lightapi/project/{id}/members
     *
     * The roster the 参加ユーザー pane shows: every user the project's
     * manager / members / viewers columns name or its member / viewer
     * organizations bring, each carrying the strongest of those roles; the
     * organizations the project names, plus the ones those users belong to (the
     * "teams" of the Backlog screen this mirrors). Read-only, and gated on
     * seeing the project row - the same columns sit on the record itself.
     */
    public function projectMembers(Request $request, $id): JsonResponse
    {
        $def = PortalDefinition::config();
        $table = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($table)) { return $this->err(404, 'no_project_table'); }

        $row = $table->getValueModel()->find($id);
        if (!$row) { return $this->err(404, 'not_found'); }
        $access = $row->enableAccess();
        if ($access !== true) { return $this->err(403, 'forbidden', $access); }

        // Everyone the project names and everyone its organizations bring,
        // each with the strongest role they reach it by (ISS00333).
        $roleOf = PortalMembership::roles((array) $row->value);

        $users = [];
        $teams = [];
        $rank = ['viewer' => 0, 'member' => 1, 'manager' => 2];
        // The organizations the project names itself come first, with the
        // role their column gives - even one that has nobody in it yet.
        $orgRoles = PortalMembership::orgRoles((array) $row->value);
        if (!empty($orgRoles)) {
            try {
                $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
                $named = $orgTable ? $orgTable->getValueModel()->newQuery()->whereIn('id', array_keys($orgRoles))->get() : collect();
                foreach ($named as $org) {
                    $teams[$org->id] = [
                        'id' => intval($org->id),
                        'name' => (string) $org->getLabel(),
                        'role' => $orgRoles[intval($org->id)],
                        'since' => $org->created_at ? PortalTime::format($org->created_at, 'Y/m/d') : null,
                        'named' => true,
                    ];
                }
            } catch (\Throwable $e) {
                \Log::warning('pm_portal member org lookup failed: ' . $e->getMessage());
            }
        }
        $userTable = CustomTable::getEloquent(SystemTableName::USER);
        if ($userTable && !empty($roleOf)) {
            $userRows = $userTable->getValueModel()->newQuery()
                ->whereIn('id', array_keys($roleOf))->get();

            // Organizations arrive via the org->user many-to-many pivot: one
            // query over the pivot, one over the organization rows.
            $orgNames = [];
            try {
                $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
                $relation = $orgTable
                    ? CustomRelation::getRelationByParentChild($orgTable, $userTable, RelationType::MANY_TO_MANY)
                    : null;
                if ($relation) {
                    $links = \DB::table($relation->getRelationName())
                        ->whereIn('child_id', array_keys($roleOf))->get();
                    $orgIds = $links->pluck('parent_id')->unique()->values()->all();
                    $orgRows = empty($orgIds) ? collect() : $orgTable->getValueModel()
                        ->newQuery()->whereIn('id', $orgIds)->get()->keyBy('id');
                    foreach ($links as $link) {
                        $org = $orgRows->get($link->parent_id);
                        $role = array_get($roleOf, intval($link->child_id));
                        if (!$org || $role === null) { continue; }
                        $orgNames[intval($link->child_id)][] = (string) $org->getLabel();
                        $known = array_get($teams, $link->parent_id);
                        // A team the project names keeps the role it was
                        // given; the others show their strongest member's.
                        if (!$known || (empty($known['named']) && $rank[$role] > $rank[$known['role']])) {
                            $teams[$link->parent_id] = [
                                'id' => intval($link->parent_id),
                                'name' => (string) $org->getLabel(),
                                'role' => $role,
                                'since' => $org->created_at ? PortalTime::format($org->created_at, 'Y/m/d') : null,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('pm_portal member org lookup failed: ' . $e->getMessage());
            }

            foreach ($userRows as $u) {
                $users[] = [
                    'id' => $u->id,
                    'code' => strval(array_get($u->value, 'user_code')),
                    'name' => (string) $u->getLabel(),
                    'role' => array_get($roleOf, $u->id, 'member'),
                    'orgs' => array_values(array_unique(array_get($orgNames, $u->id, []))),
                ];
            }
            usort($users, function ($a, $b) use ($rank) {
                return [$rank[$b['role']], $a['name']] <=> [$rank[$a['role']], $b['name']];
            });
        }

        return response()->json([
            'ok' => true,
            'teams' => array_values($teams),
            'users' => $users,
        ]);
    }

    /**
     * GET lightapi/card/{kind}/{id}?project={projectId}
     *
     * The panel behind an @mention or an issue link. Deliberately thin: the
     * portal already holds every issue of the project it has open, so this
     * answers for what the browser cannot work out by itself - who somebody
     * is - and for an issue some other screen linked to. It is read on hover,
     * so it has to come back in a fraction of the time a click would allow;
     * everything here is a primary-key read or one pivot lookup.
     *
     * Gated on the project, the same way the member roster is: whoever may
     * see the project may see who works on it. Without a project that can be
     * seen, nothing is answered at all.
     */
    public function card(Request $request, string $kind, $id): JsonResponse
    {
        $project = $this->cardProject($request);
        if (!$project) { return $this->err(403, 'forbidden'); }

        if ($kind === 'user') { return $this->userCard($project, $id); }
        if ($kind === 'issue') { return $this->issueCard($id); }

        return $this->err(404, 'unknown_card');
    }

    /**
     * The project the hover happened on, or null when the caller may not see
     * it. One primary-key read plus Exment's own row-level check.
     *
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected function cardProject(Request $request)
    {
        $id = $request->get('project');
        if (!is_numeric($id)) { return null; }
        $table = CustomTable::getEloquent(array_get(PortalDefinition::config(), 'project_table'));
        if (!isset($table)) { return null; }
        $row = $table->getValueModel()->find(intval($id));
        if (!$row || $row->enableAccess() !== true) { return null; }

        return $row;
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $id
     */
    protected function userCard($project, $id): JsonResponse
    {
        $userTable = CustomTable::getEloquent(SystemTableName::USER);
        if (!isset($userTable)) { return $this->err(404, 'no_user_table'); }
        $row = $userTable->getValueModel()->find($id);
        if (!$row) { return $this->err(404, 'not_found'); }

        return response()->json([
            'ok' => true,
            'kind' => 'user',
            'id' => intval($row->id),
            'name' => strval($row->getLabel()),
            // The login id, which is what the member roster already shows to
            // anyone who can open the project. The mail address is not here:
            // nothing on this card needs it.
            'code' => strval(array_get($row->value, 'user_code')),
            'orgs' => $this->organisationsOf($userTable, intval($row->id)),
            'role' => $this->projectRoleOf($project, intval($row->id)),
        ]);
    }

    /**
     * The organizations a user belongs to. Two queries: the org->user pivot,
     * then the organization rows it named.
     *
     * @return array<int, string>
     */
    protected function organisationsOf(CustomTable $userTable, int $userId): array
    {
        try {
            $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
            $relation = $orgTable
                ? CustomRelation::getRelationByParentChild($orgTable, $userTable, RelationType::MANY_TO_MANY)
                : null;
            if (!$relation) { return []; }
            $orgIds = \DB::table($relation->getRelationName())
                ->where('child_id', $userId)->pluck('parent_id')->unique()->values()->all();
            if (empty($orgIds)) { return []; }
            $names = [];
            foreach ($orgTable->getValueModel()->newQuery()->whereIn('id', $orgIds)->get() as $org) {
                $names[] = strval($org->getLabel());
            }

            return array_values(array_unique($names));
        } catch (\Throwable $e) {
            \Log::warning('pm_portal card org lookup failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Strongest of the roles the project names this user in, or null when it
     * names them in none - by name or through an organization the project
     * names. A project without organizations is read off the row in hand, at
     * no query; organizations cost one lookup, remembered for a few seconds.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     */
    protected function projectRoleOf($project, int $userId): ?string
    {
        // Organizations included (ISS00333). Without organization columns
        // filled in this reads the row alone, with no query.
        return PortalMembership::roles((array) $project->value)[$userId] ?? null;
    }

    /**
     * One issue, in the shape the portal's own rows use, for a link to an
     * issue the open screen never loaded. Raw column values only: the status,
     * type and priority lists are master data the portal already holds, so
     * sending their labels again would be sending them twice.
     *
     * @param mixed $id
     */
    protected function issueCard($id): JsonResponse
    {
        $def = PortalDefinition::config();
        $table = CustomTable::getEloquent(array_get($def, 'issue_table'));
        if (!isset($table)) { return $this->err(404, 'no_issue_table'); }
        $row = $table->getValueModel()->find($id);
        if (!$row) { return $this->err(404, 'not_found'); }
        $access = $this->readable($table, $row);
        if ($access !== true) { return $this->err(403, 'forbidden', $access); }

        $is = (array) array_get($def, 'issue', []);
        $get = function ($key) use ($row, $is) {
            $column = array_get($is, $key);
            return is_nullorempty($column) ? null : array_get($row->value, $column);
        };
        // Not map('intval'): map() passes the key as intval's base, which
        // turned every assignee after the first into 0.
        $assignees = collect((array) $get('assignees'))
            ->filter(function ($v) { return is_numeric($v); })
            ->map(function ($v) { return intval($v); })->values()->all();

        return response()->json([
            'ok' => true,
            'kind' => 'issue',
            'id' => intval($row->id),
            // The key the rest of the UI shows. Built from the project this
            // issue belongs to, which is not the one being looked at - that
            // is the whole reason this endpoint was asked.
            'key' => $this->issueCardKey($row),
            'subject' => strval($get('subject')),
            'st' => strval($get('status')),
            // The label as its own project spells it. Status and type can be
            // per-project master rows, so the page looking at the card may have
            // no entry for these keys at all.
            'st_l' => $this->cardLabel($table, array_get($is, 'status'), $get('status')),
            'ty' => strval($get('type')),
            'ty_l' => $this->cardLabel($table, array_get($is, 'type'), $get('type')),
            'pr' => strval($get('priority')),
            'as' => $assignees,
            'dd' => $get('due'),
            'pg' => is_numeric($get('progress')) ? floatval($get('progress')) : null,
            'users' => $this->cardUsers($assignees),
        ]);
    }

    /**
     * The visible label of one select value, whichever shape the list is in.
     *
     * A portal install keeps status / type either as a word list baked into
     * the column or as rows of a table each project owns. The card only needs
     * the text, so this stops short of the colour and ordering that
     * PmPortalController::selectMeta builds for the page itself.
     *
     * @param mixed $value
     */
    protected function cardLabel($custom_table, $column_name, $value): string
    {
        if (is_nullorempty($column_name) || is_nullorempty($value)) { return ''; }
        $custom_column = CustomColumn::getEloquent($column_name, $custom_table);
        if (!isset($custom_column)) { return ''; }

        if ($custom_column->column_type == ColumnType::SELECT_TABLE) {
            $target = $custom_column->select_target_table;
            if (!isset($target)) { return ''; }
            $row = $target->getValueModel()->find($value);

            return $row ? strval($row->getLabel()) : '';
        }

        $options = $custom_column->createSelectOptions();

        return strval(array_get($options, strval($value), ''));
    }

    /**
     * The display key of an issue that does not belong to the project on
     * screen: its own project's key, plus its id.
     *
     * Mirrors PmPortalController::issueKey. An issue is tied to its project
     * by Exment's parent relation, so one extra lookup is all it takes; an
     * orphan row falls back to the same '#' the controller uses.
     */
    protected function issueCardKey($row): string
    {
        $key = '';
        $def = PortalDefinition::config();
        $project_table = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (isset($project_table) && !is_nullorempty($row->parent_id)) {
            $project = $project_table->getValueModel()->find($row->parent_id);
            if ($project) {
                $key = strtoupper(strval(array_get($project->value, array_get($def['project'], 'key'), '')));
            }
        }

        return ($key !== '' ? $key : '#') . '-' . $row->id;
    }

    /**
     * Just enough about a handful of users to draw their avatars, in the same
     * shape the portal's own user map uses.
     *
     * @param array<int, int> $ids
     * @return array<string, array<string, string>>
     */
    protected function cardUsers(array $ids): array
    {
        if (empty($ids)) { return []; }
        $table = CustomTable::getEloquent(SystemTableName::USER);
        if (!isset($table)) { return []; }
        $avatars = PortalMembership::avatars($ids);
        $out = [];
        foreach ($table->getValueModel()->newQuery()->whereIn('id', $ids)->get() as $user) {
            $name = strval($user->getLabel());
            $out[strval($user->id)] = [
                'n' => $name,
                'i' => PortalMembership::initials($name),
                'c' => PortalMembership::avatarColor($name),
                // The avatar the user registered in Exment, if any (ISS00254).
                'a' => array_get($avatars, strval($user->id), ''),
            ];
        }

        return $out;
    }

    /**
     * How many rows elsewhere point at this one, either through a
     * select_table column or as a child of a one-to-many relation. Used to
     * refuse a delete that would leave dangling values or orphaned children.
     *
     * @param array<int, string> $owned child table names the caller removes
     *                                  itself, so they must not block it
     */
    protected function countReferences(CustomTable $table, $row, array $owned = []): int
    {
        $count = 0;

        // Counted whoever is asking. Through the caller's row scope a
        // reference they cannot see counted as none, so a status still used
        // by fifty issues read as free to delete for anyone those issues were
        // hidden from.
        foreach (CustomColumn::selectTargetTable($table->id)->get() as $column) {
            $owner = $column->custom_table;
            if (!isset($owner)) { continue; }

            $query = $owner->getValueModel()->newQuery()->withoutGlobalScope(CustomValueModelScope::class);
            // Only a search-enabled column has a physical column_<suuid> to
            // filter on; everything else lives inside the JSON. Asking for
            // the index name regardless produces SQL against a column that
            // was never created, which used to abort the whole delete.
            if ($column->index_enabled) {
                $query->where($column->getIndexColumnName(), strval($row->id));
            } else {
                $query->whereJsonContains("value->{$column->column_name}", strval($row->id));
            }
            $count += $query->count();
        }

        // Children hang off parent_id / parent_type rather than a column, so
        // the loop above cannot see them: without this a project could be
        // deleted while its issues, wiki pages and per-project master lists
        // stayed behind, pointing at an id that no longer resolves.
        foreach (CustomRelation::getRelationsByParent($table, RelationType::ONE_TO_MANY) as $relation) {
            $child = $relation->child_custom_table;
            if (!isset($child)) { continue; }
            if (in_array($child->table_name, $owned, true)) { continue; }

            $count += $child->getValueModel()->newQuery()
                ->withoutGlobalScope(CustomValueModelScope::class)
                ->where('parent_id', $row->id)
                ->where('parent_type', $table->table_name)
                ->count();
        }

        return $count;
    }

    /**
     * Child tables a row brings into being by existing, and takes with it
     * when it goes.
     *
     * A new project is handed its own copy of the status and issue type
     * lists the moment it is created (PortalUpgrade::seedProject), because
     * the create-issue form reads its defaults from them. Those rows are
     * part of the project, not things that reference it, so counting them
     * as references made every project undeletable from birth - a project
     * created by mistake could never be removed, and the message blamed ten
     * rows the user had never seen. Issues and wiki pages are not listed
     * here: those are content, and losing them silently is the thing the
     * delete guard is for.
     *
     * @return array<int, string>
     */
    protected function ownedChildTables(CustomTable $table): array
    {
        $names = PortalUpgrade::names();
        if ($table->table_name !== array_get($names, 'project_table')) {
            return [];
        }

        $owned = [];
        foreach (['status_table', 'issue_type_table'] as $role) {
            $name = array_get($names, $role);
            if (!is_nullorempty($name)) {
                $owned[] = $name;
            }
        }

        return $owned;
    }

    /**
     * Remove the rows a project owns, so the project itself can go.
     *
     * Soft deleted, like the project: Exment keeps both in the same trash,
     * so a restore brings back a project that still has its lists.
     *
     * @param array<int, string> $owned
     * @return void
     */
    protected function deleteOwnedChildren(CustomTable $table, $row, array $owned)
    {
        foreach ($owned as $name) {
            $child = CustomTable::getEloquent($name);
            if (!isset($child)) { continue; }

            // All of them, whoever is asking: a row the caller cannot see is
            // still the project's, and left behind it would point at nothing.
            $rows = $child->getValueModel()->newQuery()
                ->withoutGlobalScope(CustomValueModelScope::class)
                ->where('parent_id', $row->id)
                ->where('parent_type', $table->table_name)
                ->get();

            foreach ($rows as $one) {
                // Same rule as the row being deleted: what goes away takes
                // its stars and watches with it.
                Star::purge($name, $one->id);
                $one->delete();
            }
        }
    }

    /**
     * Read an optional parent relation from the request body.
     *
     * Returns null when the caller did not ask for one, an error
     * response when the pair is incomplete or points at something that
     * does not exist, and ['id' => int, 'type' => string] otherwise. The
     * parent table has to be a real custom table and the parent row has
     * to be one the caller may write under - editable, or merely readable
     * when the table turns editable_with_parent off - so this cannot be
     * used to attach a row to data the user may only look at.
     *
     * Readable is not on its own enough, though: the pair arrives in the
     * request body, so without the relation check below a caller could
     * file an issue under a wiki page, or any other row they happen to
     * be able to read. Nothing rejects that afterwards and no screen
     * lists it, because every portal list looks for rows whose parent is
     * a project - so the row is simply lost. Only a declared one-to-many
     * parent of this table is accepted.
     *
     * @return array<string, mixed>|JsonResponse|null
     */
    protected function resolveParent(Request $request, CustomTable $table)
    {
        $parentId = $request->input('parent_id', $request->input('value.parent_id'));
        $parentType = $request->input('parent_type', $request->input('value.parent_type'));
        if (is_nullorempty($parentId) && is_nullorempty($parentType)) {
            return null;
        }
        if (is_nullorempty($parentId) || is_nullorempty($parentType)) {
            // parent_id and parent_type only make sense together.
            return $this->err(400, 'parent_incomplete');
        }

        $parentTable = CustomTable::getEloquent($parentType);
        if (!$parentTable) {
            return $this->err(400, 'parent_table_not_found', strval($parentType));
        }
        if (!CustomRelation::getRelationByParentChild($parentTable, $table, RelationType::ONE_TO_MANY)) {
            return $this->err(400, 'parent_not_related', $parentTable->table_name . ' -> ' . $table->table_name);
        }
        $parentRow = $parentTable->getValueModel()->find($parentId);
        if (!$parentRow) {
            return $this->err(404, 'parent_not_found', $parentType . ':' . $parentId);
        }
        if (!$parentTable->hasPermissionData($parentRow)) {
            return $this->err(403, 'parent_forbidden', ErrorCode::PERMISSION_DENY());
        }

        // Readable is the floor, not the rule. Exment creates, edits and
        // deletes a child row only where its parent is editable, unless the
        // table turns editable_with_parent off - the admin grid and show
        // screens drop those actions otherwise. The parent arrives in the
        // body here, so without the same check a project's viewer could file
        // issues and wiki pages into it, or move a row of their own into it.
        $allowed = boolval($table->getOption('editable_with_parent') ?? 1)
            ? PortalDefinition::enableEdit($parentRow, true)
            : $parentRow->enableAccess();
        if ($allowed !== true) {
            return $this->err(403, 'parent_forbidden', $allowed instanceof ErrorCode ? $allowed : ErrorCode::PERMISSION_DENY());
        }

        return ['id' => intval($parentRow->id), 'type' => $parentTable->table_name];
    }

    /**
     * Is this the issue-link table? Asked by the endpoints that have no row to
     * check against, and so can only refuse the table outright.
     */
    protected function isIssueLinkTable(CustomTable $table): bool
    {
        return $table->table_name === strval(array_get(PortalDefinition::config(), 'related_table'));
    }

    /**
     * A write to the issue-link table, judged on the issues it joins.
     *
     * A link row names two issues but belongs to no project, so no role or
     * share can say who may touch it. Members used to hold edit_all on the
     * table, which let Exment's own grid and REST API list, repoint and
     * delete every link in the space; those grants are gone, and the portal
     * applies PortalRules::linkRefusal() here instead - the same rule the
     * saving event and the destroy validator apply on every other path.
     *
     * Returns null for every other table, so callers can apply it blindly.
     *
     * @param array<string, mixed>|null $value the columns about to be written;
     *        null for a delete
     * @param mixed $row the link being changed or deleted; null on create
     */
    protected function guardIssueLink(CustomTable $table, ?array $value, $row = null): ?JsonResponse
    {
        if (!$this->isIssueLinkTable($table)) {
            return null;
        }

        $old = $row ? (array) $row->value : null;
        $new = $value === null ? null : array_merge($old ?? [], $value);
        $refusal = PortalRules::linkRefusal(PortalDefinition::config(), $old, $new);
        if ($refusal !== null) {
            return $this->err($refusal === 'link_invalid' ? 400 : 403, $refusal);
        }
        if ($new !== null && PortalRules::linkTaken($table, $new, $row ? intval($row->id) : null)) {
            return $this->err(409, 'link_duplicate');
        }

        return null;
    }

    /**
     * A row by id, through the caller's row scope - except a link row, which
     * no share reaches any more and which readable() judges by its issues.
     *
     * @param CustomTable $table
     * @param mixed $id
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected function findRow(CustomTable $table, $id)
    {
        if (!is_scalar($id) || !is_numeric($id)) {
            return null;
        }
        if (!$this->isIssueLinkTable($table)) {
            return $table->getValueModel()->find($id);
        }

        return $table->getValueModel()->newQuery()
            ->withoutGlobalScope(CustomValueModelScope::class)
            ->find($id);
    }

    /**
     * Whether the caller may read this row.
     *
     * Exment's answer, and then the project's: a row inside a project is
     * readable only while its project is (see whereReadableProject()). A
     * link is readable when both of its issues are - it answers with both
     * ids and, through the labels, both titles.
     *
     * @return true|ErrorCode
     */
    protected function readable(CustomTable $table, $row)
    {
        if ($this->isIssueLinkTable($table)) {
            $def = PortalDefinition::config();
            $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
            if (!isset($issueTable)) {
                return ErrorCode::PERMISSION_DENY();
            }
            foreach (['from', 'to'] as $side) {
                $issueId = array_get((array) $row->value, strval(array_get($def, 'related_row.' . $side)));
                $issue = is_numeric($issueId) ? $issueTable->getValueModel()->find($issueId) : null;
                if (!isset($issue) || $issue->enableAccess() !== true) {
                    return ErrorCode::PERMISSION_DENY();
                }
            }

            return true;
        }

        $access = $row->enableAccess();
        if ($access !== true) {
            return $access;
        }

        $projects = $this->readableProjects($table);
        $project = $this->projectOf($table, $row);

        return $projects === null || $project === null || in_array(intval($project), $projects, true)
            ? true
            : ErrorCode::PERMISSION_DENY();
    }

    /**
     * Narrow a query on a table whose rows belong to a project to the
     * projects the caller can open.
     *
     * Issues and wiki pages hang off their project; their status and type
     * lists do too; milestones and categories name theirs in a column. The
     * roles hand every portal user every row of the lists, and a share left
     * on an issue outlives its owner's place on the project - so without
     * this a viewer read every project's milestones, categories and notes,
     * and someone taken off a project kept reading the issues they had once
     * opened. Rows that belong to no project stay visible.
     *
     * @param \Illuminate\Database\Eloquent\Builder $model
     */
    protected function whereReadableProject($model, CustomTable $table): void
    {
        $projects = $this->readableProjects($table);
        if ($projects === null) {
            return;
        }

        $column = $this->projectColumn($table);
        if ($column === null) {
            $owner = $this->scopeOwner($table);
            $model->where(function ($w) use ($owner, $projects) {
                $w->whereNull('parent_id')
                    ->orWhere('parent_type', '<>', $owner->table_name)
                    ->orWhereIn('parent_id', $projects);
            });

            return;
        }

        $key = $column->getQueryKey();
        $ids = array_map('strval', $projects);
        $model->where(function ($w) use ($key, $ids) {
            $w->whereNull($key)->orWhere($key, '')->orWhereIn($key, $ids);
        });
    }

    /**
     * The ids of the projects the caller can open, or null when the table's
     * rows do not belong to projects (or the caller can open them all).
     *
     * @return array<int, int>|null
     */
    protected function readableProjects(CustomTable $table): ?array
    {
        $def = PortalDefinition::config();
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($projectTable) || $table->table_name === $projectTable->table_name) {
            return null;
        }

        $owner = $this->scopeOwner($table);
        $ownedByProject = isset($owner) && $owner->table_name === $projectTable->table_name;
        if (!$ownedByProject && $this->projectColumn($table) === null) {
            return null;
        }

        // Whoever reads every project needs no list of them.
        if ($projectTable->hasPermission(Permission::AVAILABLE_ALL_CUSTOM_VALUE)) {
            return null;
        }

        if (!isset($this->readableProjectIds)) {
            $this->readableProjectIds = $projectTable->getValueModel()->newQuery()->pluck('id')
                ->map(function ($id) {
                    return intval($id);
                })->all();
        }

        return $this->readableProjectIds;
    }

    /**
     * The column through which a list names its project - milestones and
     * categories - or null for a table that does not.
     *
     * @return CustomColumn|null
     */
    protected function projectColumn(CustomTable $table)
    {
        $def = PortalDefinition::config();
        foreach (['milestone', 'category'] as $role) {
            if ($table->table_name === strval(array_get($def, $role . '_table'))) {
                return CustomColumn::getEloquent(strval(array_get($def, $role . '.project')), $table);
            }
        }

        return null;
    }

    /**
     * The id of the project a row belongs to, or null.
     *
     * @return mixed
     */
    protected function projectOf(CustomTable $table, $row)
    {
        $column = $this->projectColumn($table);
        if ($column !== null) {
            $value = array_get((array) $row->value, $column->column_name);

            return is_nullorempty($value) || !is_scalar($value) ? null : $value;
        }

        $owner = $this->scopeOwner($table);
        if (!isset($owner) || $row->parent_type !== $owner->table_name) {
            return null;
        }

        return is_nullorempty($row->parent_id) ? null : $row->parent_id;
    }

    /**
     * Whether rows of this table live inside a project: issues and wiki
     * pages. A new one needs a project to go in (PortalRules::placement()).
     */
    protected function livesInProject(CustomTable $table): bool
    {
        $def = PortalDefinition::config();

        return in_array($table->table_name, [strval(array_get($def, 'issue_table')), strval(array_get($def, 'wiki_table'))], true);
    }

    /**
     * The columns a client may write: every custom column but the ones the
     * system fills in. An auto number keeps whatever value it is handed -
     * Exment numbers only a row whose field is empty - so a client could
     * pick an issue key of its own, or copy another issue's.
     *
     * @return array<string, bool>
     */
    protected function writableColumns(CustomTable $table): array
    {
        $names = [];
        foreach ($table->custom_columns as $c) {
            if ($c->column_type !== ColumnType::AUTO_NUMBER) {
                $names[$c->column_name] = true;
            }
        }

        return $names;
    }

    /**
     * Every table a portal column picks from, for lookup(): the user and
     * organization pickers, and whatever a select_table column points at -
     * including a table a deployment added a column for.
     *
     * @return array<int, string>
     */
    protected function lookupTargets(): array
    {
        $names = [SystemTableName::USER, SystemTableName::ORGANIZATION];
        foreach ($this->portalTables() as $name) {
            $table = CustomTable::getEloquent($name);
            if (!isset($table)) {
                continue;
            }
            foreach ($table->custom_columns as $c) {
                if ($c->column_type !== ColumnType::SELECT_TABLE) {
                    continue;
                }
                $target = CustomTable::getEloquent(array_get($c->options ?? [], 'select_target_table'));
                if (isset($target)) {
                    $names[] = $target->table_name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    protected function resolveTable(string $tableKey, array $extra = [])
    {
        $table = CustomTable::getEloquent($tableKey);
        if (!$table) { return $this->err(404, 'table_not_found'); }
        // Only the portal's own tables are reachable through this API. A core
        // table such as comment is all-user editable, so its table-level
        // enableAccess() would otherwise wave any logged-in user through to
        // another project's rows. $extra lets lookup() add the tables its
        // pickers read, which return id + label only.
        if (!in_array($table->table_name, array_merge($this->portalTables(), $extra), true)) {
            return $this->err(404, 'table_not_found');
        }
        // The link table carries no role grants for members any more; every
        // endpoint that touches it judges the row by its issues instead.
        if ($this->isIssueLinkTable($table)) {
            return $table;
        }
        $access = $table->enableAccess();
        if ($access !== true) { return $this->err(403, 'forbidden', $access); }
        return $table;
    }

    /**
     * The table_names this API is allowed to touch: the portal's own tables,
     * read from the definition so a renamed or deployment-overridden table
     * still resolves. Every key ending in _table holds a table name.
     *
     * @return array<int, string>
     */
    protected function portalTables(): array
    {
        $names = [];
        foreach (PortalDefinition::config() as $key => $value) {
            if (is_string($value) && substr($key, -6) === '_table') {
                $names[] = $value;
            }
        }
        return $names;
    }

    /**
     * A refusal: the code in `error` for the client to branch on, the
     * Japanese sentence in `message` for it to show. `detail` is data - a
     * count, an id - and never the text of an exception.
     *
     * @param ErrorCode|string|true|null $detail
     */
    protected function err(int $code, string $key, $detail = null): JsonResponse
    {
        $extra = [];
        // An ErrorCode cast to a string is a bare number such as "101".
        if ($detail instanceof ErrorCode) {
            $extra['code'] = strval($detail);
            // A core refusal more precise than "no permission" - a workflow
            // lock, a missing row - is already a Japanese sentence; keep it
            // over the generic one for the code.
            if (strval($detail) !== ErrorCode::PERMISSION_DENY) {
                $extra['message'] = $this->reasonText($detail);
            }
        }
        if ($detail !== null && $detail !== true) { $extra['detail'] = $this->reasonText($detail); }

        $count = is_scalar($detail) ? strval($detail) : '';
        return PortalDefinition::jsonError($code, $key, $extra, ['count' => $count]);
    }

    /**
     * A 500 for a failure nobody planned for. The exception text goes to the
     * log only: a QueryException prints the SQL, the host and the database
     * name, and none of it is anything a person on the screen can act on.
     */
    protected function failed(string $key, \Throwable $e): JsonResponse
    {
        // ISS00261: Exment's editor column reads every markdown image
        // "![..](src)" and asks the storage whether src exists; a src such as
        // "..." becomes the filename ".." and Flysystem throws. Until Exment
        // itself is fixed, say what to change instead of a bare 500.
        if ($e instanceof \League\Flysystem\PathTraversalDetected
            || stripos($e->getMessage(), 'Path traversal detected') !== false) {
            Log::warning('pm_portal lightapi ' . $key . ': markdown image with an invalid location: ' . $e->getMessage());
            $message = '本文の画像 ![…](…) の ( ) の中が、画像の場所になっていません。画像の URL にするか、先頭の ! を消して保存してください。';

            return response()->json([
                'ok' => false,
                'error' => 'validation',
                'message' => $message,
                'fields' => ['description' => [$message]],
            ], 422);
        }

        Log::error('pm_portal lightapi ' . $key . ': ' . $e->getMessage(), ['exception' => $e]);

        return $this->err(500, $key);
    }

    /**
     * The 422 for a value the table's validator refused, with the per-field
     * sentences the form puts under each input.
     */
    protected function invalid(ValidationException $e): JsonResponse
    {
        $fields = $this->validationFields($e);
        $extra = ['fields' => $fields];

        // The first field's own sentence says more than "the input is wrong",
        // and a screen with no input for that field - the dock, a quick add
        // box - shows nothing else.
        foreach ($fields as $messages) {
            $first = is_array($messages) ? reset($messages) : null;
            if (is_string($first) && $first !== '') {
                $extra['message'] = $first;
                break;
            }
        }

        return PortalDefinition::jsonError(422, 'validation', $extra);
    }

    /**
     * @param ErrorCode|string|mixed $reason
     */
    protected function reasonText($reason): string
    {
        return $reason instanceof ErrorCode ? strval($reason->getMessage()) : strval($reason);
    }

    /**
     * Return the columns the client asked for, or all of them. Names are
     * validated against the table so a bad ?columns= list cannot leak
     * unrelated database columns.
     */
    protected function pickColumns(CustomTable $table, $requested): array
    {
        $all = [];
        foreach ($table->custom_columns as $c) { $all[$c->column_name] = $c; }

        if (!$requested) { return $all; }

        $names = array_filter(array_map('trim', explode(',', (string) $requested)));
        $out = [];
        foreach ($names as $n) {
            if (isset($all[$n])) { $out[$n] = $all[$n]; }
        }
        return $out;
    }

    protected function describeColumns(CustomTable $table, array $scope = []): array
    {
        $out = [
            ['name' => 'id',         'label' => 'ID',                            'type' => 'id',       'readonly' => true],
            ['name' => 'created_at', 'label' => exmtrans('common.created_at'), 'type' => 'datetime', 'readonly' => true],
            ['name' => 'updated_at', 'label' => exmtrans('common.updated_at'), 'type' => 'datetime', 'readonly' => true],
        ];

        $prefix = $this->apiUrl('lookup');
        $roTypes = ['auto_number', 'calc']; // system-generated, user cannot type into these
        foreach ($table->custom_columns as $c) {
            $opts = $c->options ?? [];
            $entry = [
                'name' => $c->column_name,
                'label' => $c->column_view_name,
                'type' => $this->mapType($c->column_type),
                'raw_type' => $c->column_type,
                'required' => !empty($opts['required']),
                'help' => $opts['help'] ?? null,
                'default' => $opts['default'] ?? null,
                'multiple' => !empty($opts['multiple_enabled']),
                'readonly' => in_array($c->column_type, $roTypes, true),
            ];

            if ($c->column_type === 'select_valtext' && !empty($opts['select_item_valtext'])) {
                $entry['options'] = $this->parseValtextOptions($opts['select_item_valtext'], $opts['grid_value_colors'] ?? null);
            } elseif ($c->column_type === 'select' && !empty($opts['select_item'])) {
                $entry['options'] = $this->parseValueOptions($opts['select_item']);
            } elseif ($c->column_type === 'select_table' && !empty($opts['select_target_table'])) {
                // getEloquent is process-cached; find() would hit the DB per column.
                $target = CustomTable::getEloquent($opts['select_target_table']);
                if ($target) {
                    $entry['target_table'] = $target->table_name;
                    // Whether a quick add box would have anywhere to write.
                    // Members can read the milestone and category lists but
                    // not add to them, and a button that can only ever fail
                    // is worse than no button.
                    $entry['can_create'] = $target->enableCreate(true) === true;
                    $entry['lookup_url'] = $this->scopedLookupUrl(
                        "$prefix/{$target->table_name}",
                        $table,
                        $target,
                        $c->column_name,
                        $scope
                    );
                }
                // This column stores a row id. A default left over from a word
                // list would be offered by the form and then dropped by the
                // save, so never advertise one the target table cannot match.
                if ($entry['default'] !== null && !ctype_digit(strval($entry['default']))) {
                    $entry['default'] = null;
                }
            } elseif ($c->column_type === 'user') {
                $entry['lookup_url'] = "$prefix/user";
            } elseif ($c->column_type === 'organization') {
                $entry['lookup_url'] = "$prefix/organization";
            }

            $out[] = $entry;
        }
        return $out;
    }

    /**
     * Add to a lookup URL whatever narrows it to the row being edited.
     *
     * A list is only right once it knows where the row lives: the status list
     * seven projects share is seven copies of the same five words. The
     * narrowing rides in the URL rather than being applied afterwards because
     * the client caches and searches by URL, so two projects open in one tab
     * stay apart for free.
     *
     * @param string $url
     * @param CustomTable $table the table being edited
     * @param CustomTable $target the table the column points at
     * @param string $columnName the column the list is for
     * @param array<string, string|null> $scope
     * @return string
     */
    protected function scopedLookupUrl(string $url, CustomTable $table, CustomTable $target, string $columnName, array $scope): string
    {
        $params = [];

        $parentType = array_get($scope, 'parent_type');
        $parentId = array_get($scope, 'parent_id');
        if (!is_nullorempty($parentType) && !is_nullorempty($parentId)) {
            // Only a list owned by the same thing the row is owned by can be
            // narrowed at all.
            $owner = $this->scopeOwner($target);
            if (isset($owner) && $owner->table_name === $parentType) {
                $params['parent_id'] = $parentId;
            }
        }

        // A list that is nobody's child may still belong to a project, by
        // naming it in a column. Only reached when the relation above found
        // nothing, so a list can be narrowed one way or the other, never
        // both, and a list that is neither - priorities, resolutions - is
        // left alone as the space wide master it is.
        if (!isset($params['parent_id']) && !is_nullorempty($parentType) && !is_nullorempty($parentId)) {
            $scopeColumn = $this->ownerColumn($target, strval($parentType));
            if (isset($scopeColumn)) {
                $params['scope_column'] = $scopeColumn;
                $params['parent_id'] = $parentId;
            }
        }

        // A column pointing back at its own table is a hierarchy, and the two
        // things a hierarchy must not offer are the row itself and, where it
        // is only allowed one level, anything already hanging off something.
        if ($target->id == $table->id) {
            $exclude = array_get($scope, 'exclude');
            if (!is_nullorempty($exclude)) {
                $params['exclude'] = $exclude;
            }
            if (PortalRules::nestsOneLevel($table)) {
                $params['empty'] = $columnName;
            }
        }

        return empty($params) ? $url : $url . '?' . http_build_query($params);
    }

    /**
     * Map Exment column type to a small stable UI-side vocabulary so the
     * client does not have to know the internal enum. Anything unknown is
     * left as its raw name plus type=string as a safe fallback.
     */
    protected function mapType(string $t): string
    {
        $map = [
            'text' => 'text', 'textarea' => 'text', 'url' => 'url',
            'email' => 'email', 'file' => 'file', 'image' => 'file',
            'editor' => 'richtext', 'integer' => 'integer', 'decimal' => 'number',
            'currency' => 'number', 'date' => 'date', 'datetime' => 'datetime',
            'time' => 'time', 'boolean' => 'boolean', 'yesno' => 'boolean',
            'select' => 'select', 'select_valtext' => 'select',
            'select_table' => 'relation', 'user' => 'user',
            'organization' => 'organization', 'auto_number' => 'auto_number',
        ];
        return $map[$t] ?? 'string';
    }

    protected function parseValtextOptions(string $raw, $colorRaw = null): array
    {
        $colors = [];
        if ($colorRaw) {
            foreach (preg_split('/\r?\n/', $colorRaw) as $line) {
                $parts = explode(',', $line, 2);
                if (count($parts) === 2) { $colors[trim($parts[0])] = trim($parts[1]); }
            }
        }
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            $parts = explode(',', $line, 2);
            $key = trim($parts[0]);
            $label = trim($parts[1] ?? $parts[0]);
            $entry = ['key' => $key, 'label' => $label];
            if (isset($colors[$key])) { $entry['color'] = $colors[$key]; }
            $out[] = $entry;
        }
        return $out;
    }

    protected function parseValueOptions(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '') { $out[] = ['key' => $line, 'label' => $line]; }
        }
        return $out;
    }

    /**
     * Equality filter per column. Uses the dedicated index_enabled column
     * when possible (fast); falls back to a JSON extract otherwise.
     * A short allow-list of system columns (id, parent_id, parent_type,
     * created_at, updated_at) is also supported so callers can scope a
     * list to a parent row without inventing custom index columns.
     */
    protected function applyFilters($model, CustomTable $table, array $filters): void
    {
        static $sysCols = ['id', 'parent_id', 'parent_type', 'created_at', 'updated_at'];
        foreach ($filters as $name => $val) {
            if ($val === '' || $val === null) { continue; }
            if (in_array($name, $sysCols, true)) {
                is_array($val) ? $model->whereIn($name, $val) : $model->where($name, $val);
                continue;
            }
            $c = CustomColumn::getEloquent($name, $table);
            if (!$c) { continue; }
            $idx = $this->indexColumn($c);
            if ($idx) {
                is_array($val) ? $model->whereIn($idx, $val) : $model->where($idx, $val);
            } else {
                $model->whereJsonContains("value->{$c->column_name}", $val);
            }
        }
    }

    /**
     * Case-insensitive LIKE search across every indexed text-ish column
     * plus the table's label column. Keeps the query small enough that a
     * casual /?search= call does not scan a table full of JSON blobs.
     */
    protected function applySearch($model, CustomTable $table, $q): void
    {
        $q = trim((string) $q);
        if ($q === '') { return; }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $searchable = [];
        foreach ($table->custom_columns as $c) {
            if (!in_array($c->column_type, ['text', 'textarea', 'url', 'email', 'auto_number'], true)) { continue; }
            $idx = $this->indexColumn($c);
            if ($idx) { $searchable[] = $idx; }
        }
        if (!$searchable) {
            $model->where('value', 'LIKE', $like);
            return;
        }

        $model->where(function ($w) use ($searchable, $like) {
            foreach ($searchable as $col) { $w->orWhere($col, 'LIKE', $like); }
        });
    }

    /**
     * ?orderby=col.desc,col2.asc  → order the query by (indexed) columns.
     * Unknown / non-indexed columns are ignored so callers cannot cause a
     * full-table sort on a JSON field.
     */
    protected function applyOrder($model, CustomTable $table, $raw): void
    {
        if (!$raw) { $model->orderBy('id', 'desc'); return; }

        $applied = 0;
        foreach (explode(',', (string) $raw) as $part) {
            $part = trim($part);
            if ($part === '') { continue; }
            $dir = 'asc';
            $name = $part;
            if (str_contains($part, '.')) {
                [$name, $dir] = explode('.', $part, 2);
                $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
            }
            if (in_array($name, ['id', 'created_at', 'updated_at'], true)) {
                $model->orderBy($name, $dir);
                $applied++;
                continue;
            }
            $c = CustomColumn::getEloquent($name, $table);
            $idx = $c ? $this->indexColumn($c) : null;
            if ($idx) { $model->orderBy($idx, $dir); $applied++; }
        }
        if (!$applied) { $model->orderBy('id', 'desc'); }
    }

    /**
     * The physical column behind a search-enabled custom column, or null.
     *
     * Only a column with index_enabled has one. Asked of any other column,
     * getIndexColumnName() still answers with a column_<suuid> name that
     * does not exist in the table, and the query that used it failed with a
     * 500 - so typing into the status or milestone picker found nothing.
     */
    protected function indexColumn(CustomColumn $column): ?string
    {
        if (!$column->index_enabled) {
            return null;
        }
        $name = $column->getIndexColumnName();

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Fast path: raw values only. Used when the client asked
     * ?with_labels=0 (e.g. building a spreadsheet-style grid).
     */
    protected function transformRow($row, array $columns): array
    {
        $out = [
            'id' => $row->id,
            'created_at' => $row->created_at ? (string) $row->created_at : null,
            'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
        ];
        $value = is_array($row->value) ? $row->value : [];
        foreach ($columns as $name => $c) {
            $v = $value[$name] ?? null;
            $out[$name] = $c && $c->column_type === ColumnType::EDITOR && isset($v) ? Editor::replaceImgUrl($v) : $v;
            // A date and time, as the user reads it (see persistRow).
            if ($c && $c->column_type === ColumnType::DATETIME && isset($v)) {
                $out[$name] = PortalTime::toUser($v);
            }
        }
        return $out;
    }

    /**
     * Enrich rows with human labels for select / user / relation columns.
     * User & relation labels are batched: one query per referenced target
     * table, then a hash-map join instead of N Eloquent finds.
     *
     * Optimisations vs. the obvious "loop per column, load target, query":
     *   - target CustomTable is only resolved AFTER we know at least one
     *     row referenced it (many edit-form opens have empty relation
     *     columns; each avoided load is a query + hydrate)
     *   - all user / organization columns share the same target table, so
     *     their ids are combined into ONE whereIn per target instead of
     *     one per column
     *   - CustomTable::getEloquent (process-cached) replaces
     *     CustomTable::find so repeated schema opens stay cache-hot
     */
    protected function transformRowsWithLabels($rows, array $columns): array
    {
        $rowsArr = [];
        foreach ($rows as $row) { $rowsArr[] = $this->transformRow($row, $columns); }
        if (!$rowsArr) { return []; }

        // Group user/organization column names by target table so we can
        // resolve every user column with a single query, not one per col.
        $userCols = [];   // ['user' => [colName, ...], 'organization' => [...]]
        // For each select_table column that needs a batched lookup, remember
        // its (name, target_id) so we do a single per-target query.
        $selectTableCols = []; // [targetId => [colName, ...]]

        foreach ($columns as $name => $col) {
            $type = $col->column_type;
            $opts = $col->options ?? [];

            if ($type === 'select_valtext') {
                $lookup = [];
                foreach ($this->parseValtextOptions($opts['select_item_valtext'] ?? '', $opts['grid_value_colors'] ?? null) as $o) {
                    $lookup[$o['key']] = $o['label'];
                }
                foreach ($rowsArr as &$r) {
                    if (isset($r[$name]) && isset($lookup[$r[$name]])) { $r[$name . '_label'] = $lookup[$r[$name]]; }
                } unset($r);
                continue;
            }

            if ($type === 'select_table') {
                $targetId = $opts['select_target_table'] ?? null;
                if (!$targetId) { continue; }
                $selectTableCols[$targetId][] = $name;
                continue;
            }

            if ($type === 'user' || $type === 'organization') {
                $userCols[$type][] = $name;
            }
        }

        // Batched select_table resolution: one query per referenced target.
        foreach ($selectTableCols as $targetId => $names) {
            $ids = [];
            $perRow = []; // rowIndex => [colName => rawValue]
            foreach ($rowsArr as $i => $r) {
                foreach ($names as $name) {
                    $v = $r[$name] ?? null;
                    if (!$v) { continue; }
                    $perRow[$i][$name] = $v;
                    if (is_array($v)) { foreach ($v as $x) { $ids[] = $x; } }
                    else { $ids[] = $v; }
                }
            }
            $ids = array_values(array_unique(array_filter($ids)));
            if (!$ids) { continue; }
            // Only now load the target table (cached) + fetch labels.
            $target = CustomTable::getEloquent($targetId);
            if (!$target) { continue; }
            $found = $target->getValueModel()->whereIn('id', $ids)->get();
            $lookup = [];
            foreach ($found as $t) { $lookup[$t->id] = (string) $t->getLabel(); }
            foreach ($perRow as $i => $vals) {
                foreach ($vals as $name => $v) {
                    $rowsArr[$i][$name . '_label'] = is_array($v)
                        ? implode('、', array_map(fn ($x) => $lookup[$x] ?? '', $v))
                        : ($lookup[$v] ?? null);
                }
            }
        }

        // Batched user/organization resolution: one query per target table
        // even when several columns of that type share the same target.
        foreach ($userCols as $type => $names) {
            $ids = [];
            $perRow = []; // rowIndex => [colName => rawValue]
            foreach ($rowsArr as $i => $r) {
                foreach ($names as $name) {
                    $v = $r[$name] ?? null;
                    if (!$v) { continue; }
                    $perRow[$i][$name] = $v;
                    if (is_array($v)) { foreach ($v as $x) { $ids[] = $x; } }
                    elseif (is_string($v) && str_contains($v, ',')) { foreach (explode(',', $v) as $x) { $ids[] = trim($x); } }
                    else { $ids[] = $v; }
                }
            }
            $ids = array_values(array_unique(array_filter($ids)));
            if (!$ids) { continue; }
            $target = CustomTable::getEloquent($type === 'user' ? 'user' : 'organization');
            if (!$target) { continue; }
            $found = $target->getValueModel()->whereIn('id', $ids)->get();
            $lookup = [];
            foreach ($found as $t) { $lookup[$t->id] = (string) $t->getLabel(); }
            foreach ($perRow as $i => $vals) {
                foreach ($vals as $name => $v) {
                    if (is_array($v)) {
                        $rowsArr[$i][$name . '_label'] = implode('、', array_map(fn ($x) => $lookup[$x] ?? '', $v));
                    } elseif (is_string($v) && str_contains($v, ',')) {
                        $parts = array_map('trim', explode(',', $v));
                        $rowsArr[$i][$name . '_label'] = implode('、', array_map(fn ($x) => $lookup[$x] ?? '', $parts));
                    } else {
                        $rowsArr[$i][$name . '_label'] = $lookup[$v] ?? null;
                    }
                }
            }
        }

        return $rowsArr;
    }
}
