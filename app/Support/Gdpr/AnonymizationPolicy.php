<?php

namespace App\Support\Gdpr;

use App\Models\User;

/**
 * TODO 12.2: mikor szabad egy felhasználót anonimizálni.
 *
 * A feltétel az UTÓDLÁS, nem a szerep. Korábban a napi parancs egy szereplistát
 * zárt ki (mainAdmin, groupCreator), ami két irányban is tévedett: védte azt a
 * groupCreator-t, akinek minden csoportját ellátja más, és nem védte azt a
 * csoportadmint, aki az egyetlen a csoportjában.
 *
 * Két szabály:
 *   1. mainAdmin csak akkor, ha marad másik, nem anonimizált mainAdmin.
 *      A `role` benne van a $gdprAnonymizableFields-ben, tehát az anonimizálás
 *      egyben le is fokoz `registered`-re - az utolsó főadmin elvesztésével az
 *      oldal adminisztrátor nélkül maradna.
 *   2. Csoportadmin csak akkor, ha MINDEN csoportjára igaz a
 *      pwbs_check_group_other_admins(). Ez ugyanaz a helper, amit a
 *      csoportelhagyás használ (Groups\ListGroups::confirmLogout()) - a két
 *      szabály nem sodródhat el egymástól.
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

    /** @var array<int, array>|null Lusta, egy példányon belül egyszer számolt. */
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
     * Anonimizálható-e a felhasználó.
     */
    public function allows(): bool
    {
        return $this->blockers() === [];
    }

    /**
     * A blokkoló okok, strukturáltan.
     *
     * Alakja: [['reason' => self::BLOCKED_*, 'groups' => ['Név', ...]], ...]
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
     * A blokkolás oka a felhasználónak, lefordítva - a profiloldali GDPR-kérés
     * használja. Nem elég csendben kihagyni a kérést: a felhasználónak tudnia
     * kell, mit tegyen (adja át a csoportját, nevezzen ki másik főadmint).
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
     * 1. szabály. Egy már anonimizált főadmin nem utód: a sora megmarad, de a
     * szerepe `registered`-re változott, és be sem tud lépni.
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
     * 2. szabály. Azok a csoportok, ahol a felhasználó elfogadott admin, és
     * nincs, aki átvegye.
     *
     * A userGroupsDeletable() reláció query alakban kell: a TODO 07.2 rögzítette,
     * hogy a lustán betöltött változat egy kérésen belül elavult adatot ad.
     *
     * @return array<int, string> a blokkoló csoportok nevei
     */
    private function groupsWithoutSuccessor(): array
    {
        $groups = [];

        foreach ($this->user->userGroupsDeletable()->get() as $group) {
            if (pwbs_check_group_other_admins($group->id, $this->user->id) === false) {
                // A groups.name `encrypted` cast, modellen keresztül olvasva
                // már a nyílt szöveg.
                $groups[] = (string) $group->name;
            }
        }

        return $groups;
    }
}
