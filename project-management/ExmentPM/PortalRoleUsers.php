<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\JoinedOrgFilterType;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValueModelScope;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\AuthUserOrgHelper;

/**
 * The users whose role edits a table's rows, by Exment's own reading of the role groups
 * (AuthUserOrgHelper::getRoleUserOrgId), with no shortcut: getRoleUserAndOrgBelongsUserQueryTable
 * answers "everyone" for a table every user may see (all_user_viewable_flg), which says nothing
 * about who may add a row.
 */
class PortalRoleUsers extends AuthUserOrgHelper
{
    /**
     * The ids of the users whose role edits rows of $table (custom_table, custom_value_edit_all,
     * custom_value_edit), system administrators and the members of an organization with such a
     * role included.
     *
     * @return array<int, int>
     */
    public static function editors(CustomTable $table): array
    {
        $direct = (array) static::getRoleUserOrgId($table, SystemTableName::USER, Permission::AVAILABLE_EDIT_CUSTOM_VALUE);
        $byOrg = [];
        if (System::organization_available()) {
            $orgIds = (array) static::getRoleUserOrgId($table, SystemTableName::ORGANIZATION, Permission::AVAILABLE_EDIT_CUSTOM_VALUE);
            if (!empty($orgIds)) {
                // the users an organization's role reaches, as Exment reads it (org_joined_type_role_group).
                // Every organization and user, not the ones the request's user may see: with
                // filter_multi_user the scope would answer for the visitor, not for the owner.
                $enum = JoinedOrgFilterType::getEnum(System::org_joined_type_role_group(), JoinedOrgFilterType::ONLY_JOIN);
                $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
                $unscoped = function ($query) {
                    $query->withoutGlobalScope(CustomValueModelScope::class);
                };
                foreach ($orgTable->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)->whereIn('id', $orgIds)->get() as $org) {
                    // @phpstan-ignore-next-line
                    $related = $orgTable->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)
                        ->with(['users' => $unscoped])->whereIn('id', (array) $org->getOrganizationIdsForQuery($enum))->get();
                    foreach ($related as $relatedOrg) {
                        foreach ($relatedOrg->users as $user) {
                            $byOrg[] = $user->getUserId();
                        }
                    }
                }
            }
        }

        return PortalRunOwner::mergeIds($direct, $byOrg);
    }
}
