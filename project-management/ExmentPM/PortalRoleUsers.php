<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\JoinedOrgFilterType;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
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
        $ids = array_map('intval', (array) static::getRoleUserOrgId($table, SystemTableName::USER, Permission::AVAILABLE_EDIT_CUSTOM_VALUE));
        if (System::organization_available()) {
            $orgIds = (array) static::getRoleUserOrgId($table, SystemTableName::ORGANIZATION, Permission::AVAILABLE_EDIT_CUSTOM_VALUE);
            if (!empty($orgIds)) {
                // the users an organization's role reaches, as Exment reads it (org_joined_type_role_group)
                $enum = JoinedOrgFilterType::getEnum(System::org_joined_type_role_group(), JoinedOrgFilterType::ONLY_JOIN);
                $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
                foreach ($orgTable->getValueModel()->query()->whereIn('id', $orgIds)->get() as $org) {
                    // @phpstan-ignore-next-line
                    foreach ($orgTable->getValueModel()->with('users')->find($org->getOrganizationIdsForQuery($enum)) as $related) {
                        foreach ($related->users as $user) {
                            $ids[] = intval($user->getUserId());
                        }
                    }
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        sort($ids);

        return $ids;
    }
}
