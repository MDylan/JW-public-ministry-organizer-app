<?php

namespace App\Support\Gdpr;

use App\Models\User;

/**
 * TODO 12.2: when a user may be anonymized.
 *
 * The condition is SUCCESSION, not role. The nightly command used to exclude
 * a role list (mainAdmin, groupCreator), which was wrong in two directions: it
 * protected a groupCreator whose every group is covered by someone else, and it
 * did not protect a group admin who is the only one in their group.
 *
 * Two rules:
 *   1. mainAdmin only if another, non-anonymized mainAdmin remains.
 *      `role` is part of $gdprAnonymizableFields, so anonymization also
 *      demotes to `registered` - losing the last main admin would leave the
 *      site without an administrator.
 *   2. Group admin only if pwbs_check_group_other_admins() holds for EVERY
 *      one of their groups. This is the same helper used by
 *      leaving a group (Groups\ListGroups::confirmLogout()) - the two
 *      rules must not drift apart from each other.
 *
 * Called from User::anonymize(), so it applies on every path that anonymizes a
 * user - the nightly gdpr:anonymize-inactive command, the profile-initiated
 * GDPR request, DeleteGroupDataProcess and the one-off backfill migration.
 * TODO 33.2 removed a fifth: the Dialect package's own 00:00 command.
 */
class AnonymizationPolicy
{
    public const BLOCKED_LAST_MAIN_ADMIN = 'last_main_admin';

    public const BLOCKED_GROUP_WITHOUT_SUCCESSOR = 'group_without_successor';

    private User $user;

    /** @var array<int, array>|null Lazily computed once per instance. */
    private ?array $blockers = null;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public static function for(User $user): self
    {
        return new self($user);
    }

    /**
     * Whether the user may be anonymized.
     */
    public function allows(): bool
    {
        return $this->blockers() === [];
    }

    /**
     * The blocking reasons, structured.
     *
     * Shape: [['reason' => self::BLOCKED_*, 'groups' => ['Name', ...]], ...]
     */
    public function blockers(): array
    {
        if ($this->blockers !== null) {
            return $this->blockers;
        }

        $blockers = [];

        if ($this->isTheLastMainAdmin()) {
            $blockers[] = ['reason' => self::BLOCKED_LAST_MAIN_ADMIN];
        }

        $groups = $this->groupsWithoutSuccessor();

        if ($groups !== []) {
            $blockers[] = [
                'reason' => self::BLOCKED_GROUP_WITHOUT_SUCCESSOR,
                'groups' => $groups,
            ];
        }

        return $this->blockers = $blockers;
    }

    /**
     * The blocking reason for the user, translated - used by the
     * profile-page GDPR request. Silently skipping the request isn't enough:
     * the user needs to know what to do (hand over their group, appoint another main admin).
     */
    public function reason(): ?string
    {
        $messages = [];

        foreach ($this->blockers() as $blocker) {
            if ($blocker['reason'] === self::BLOCKED_LAST_MAIN_ADMIN) {
                $messages[] = __('user.delete.no_successor_admin');
            }

            if ($blocker['reason'] === self::BLOCKED_GROUP_WITHOUT_SUCCESSOR) {
                $messages[] = __('user.delete.no_successor_group', [
                    'groups' => implode(', ', $blocker['groups']),
                ]);
            }
        }

        return $messages === [] ? null : implode(' ', $messages);
    }

    /**
     * Rule 1. An already anonymized main admin is not a successor: their row
     * remains, but their role has changed to `registered`, and they can't even log in.
     */
    private function isTheLastMainAdmin(): bool
    {
        if ($this->user->role !== 'mainAdmin') {
            return false;
        }

        return User::where('role', 'mainAdmin')
            ->where('isAnonymized', 0)
            ->where('id', '!=', $this->user->id)
            ->doesntExist();
    }

    /**
     * Rule 2. The groups where the user is an accepted admin and there is no
     * one to take over.
     *
     * The userGroupsDeletable() relation must be used in query form: TODO 07.2
     * established that the eagerly-loaded variant returns stale data within a request.
     *
     * @return array<int, string> the names of the blocking groups
     */
    private function groupsWithoutSuccessor(): array
    {
        $groups = [];

        foreach ($this->user->userGroupsDeletable()->get() as $group) {
            if (pwbs_check_group_other_admins($group->id, $this->user->id) === false) {
                // groups.name has an `encrypted` cast; read through the model
                // it's already plaintext.
                $groups[] = (string) $group->name;
            }
        }

        return $groups;
    }
}
