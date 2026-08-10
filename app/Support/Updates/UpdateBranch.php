<?php

namespace App\Support\Updates;

use MDylan\LaraUpdater\LaraUpdaterController;

/**
 * How far the system may update AUTOMATICALLY: within its own major branch.
 *
 * THE PROBLEM
 *
 * laraupdater's only condition is that the channel advertises something newer
 * than what's installed (`version_compare($remote, $local, '>')`). It has no PHP,
 * Laravel, or major-version limit. As soon as the 2.x line appears on the
 * channel, every 1.x installation would update itself with ONE CLICK - in
 * maintenance mode, with `migrate --force` - even though 2.x already assumes PHP 8.3+ and a
 * different Laravel major. `restore()` brings back the overwritten files, but
 * NOT the migrations that already ran: the installation would remain unusable.
 *
 * THE RULE
 *
 * We do not cross a major version automatically. What's on the current branch
 * is still a single click; anything that would move to a higher major, the system
 * only NOTIFIES about, and asks for a manual update.
 *
 * The ceiling is deliberately DERIVED, not configured: it comes from
 * version.txt's major. This way there's nothing to forget to maintain - the 2.x line
 * will be protected from 3.x the same way - and there's no config value whose
 * being emptied out would silently re-enable automatic major jumps.
 *
 * FAIL-CLOSED
 *
 * If either side's major can't be read, the answer is `false`, i.e. deny.
 * The two failure modes are not equivalent: a false denial just means the
 * admin has to update manually, while a false allow destroys a live system.
 *
 * WHAT SECURES THIS FROM THE OUTSIDE AS WELL
 *
 * This limit only protects installations on which the release containing it
 * is ALREADY RUNNING. Older ones are brought here by the vendor's `previous_version`
 * chain: if the channel head is 2.0.0, and its `previous_version` is the last 1.x
 * release, then an old installation updates to that one first - meaning
 * no one can reach 2.0.0 by bypassing the limit. The manifest's shape is therefore not
 * a matter of convenience, but part of this protection (see release/README.md).
 */
final class UpdateBranch
{
    /**
     * May this version be installed automatically?
     *
     * The caller passes in the result of laraupdater's check(), so the version here is
     * already guaranteed to be newer than what's installed; only the major matters.
     */
    public static function allows(string $version): bool
    {
        $current = self::currentMajor();
        $remote = self::majorOf($version);

        if ($current === null || $remote === null) {
            return false;
        }

        return $remote <= $current;
    }

    /**
     * The installed version's major.
     *
     * We ask the laraupdater controller for the version, rather than
     * re-reading version.txt: it has the trim(), without which the file's trailing line break
     * would break every comparison (see the dedicated test written for this
     * in UpdaterContractTest).
     */
    public static function currentMajor(): ?int
    {
        return self::majorOf((new LaraUpdaterController)->getCurrentVersion());
    }

    /**
     * A version number's major, or null if it can't be read.
     *
     * Permissive about input ('v2.0.0', '2.0.0-beta1', ' 2.0 '), because we
     * don't validate the channel's content - but anything that doesn't start with a
     * digit is not a version, and means denial on the caller's side.
     */
    public static function majorOf(string $version): ?int
    {
        if (preg_match('/^\s*v?(\d+)/', $version, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
