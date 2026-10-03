<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_nomoreai\local;

/**
 * Who and what the checks skip: exempt users, unmonitored users and trusted networks.
 *
 * @package    local_nomoreai
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exemption {
    /**
     * Whether the user is exempt from every check in this context.
     *
     * @param \context $context
     * @param int|null $userid null for the current user
     * @return bool
     */
    public static function is_exempt(\context $context, ?int $userid = null): bool {
        return has_capability('local/nomoreai:exempt', $context, $userid);
    }

    /**
     * Whether the user is exempt anywhere: site-wide, or in at least one course.
     *
     * Used for decisions that have no activity context, such as minting a token, so that a teacher using
     * the app in their own courses is never treated as a student.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_exempt_anywhere(int $userid): bool {
        if (!$userid || isguestuser($userid)) {
            return false;
        }
        if (is_siteadmin($userid) || has_capability('local/nomoreai:exempt', \context_system::instance(), $userid)) {
            return true;
        }
        return !empty(get_user_capability_course('local/nomoreai:exempt', $userid, true, '', '', 1));
    }

    /**
     * Whether browser monitoring and behavioural signals apply to the user in this context.
     *
     * @param \context $context
     * @param int|null $userid null for the current user
     * @return bool
     */
    public static function is_monitored(\context $context, ?int $userid = null): bool {
        return !self::is_exempt($context, $userid) && !has_capability('local/nomoreai:notmonitored', $context, $userid);
    }

    /**
     * Whether the request comes from a trusted network, which skips the agent checks.
     *
     * @param string|null $ip null for the current request's address
     * @return bool
     */
    public static function trusted_network(?string $ip = null): bool {
        $networks = config::lines('trustednetworks');
        if (!$networks) {
            return false;
        }
        return address_in_subnet($ip ?? getremoteaddr(), implode(',', $networks));
    }
}
