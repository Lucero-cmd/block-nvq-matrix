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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Block definition class for the block_nvq_matrix plugin.
 *
 * Renders a lightweight launcher card on the Moodle dashboard.
 * All matrix data and rendering logic lives in view.php and
 * classes/matrix_data.php — this block simply provides a
 * "View Matrix" button that opens the full-page view.
 *
 * @package   block_nvq_matrix
 * @copyright 2025 Alex D&D Training Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_nvq_matrix extends block_base {

    public function init(): void {
        $this->title = get_string('defaulttitle', 'block_nvq_matrix');
    }

    public function specialization(): void {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        }
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function applicable_formats(): array {
        return [
            'my'          => true,   // Dashboard — launcher lives here.
            'course-view' => false,
            'site-index'  => false,
            'mod'         => false,
            'admin'       => false,
        ];
    }

    public function has_config(): bool {
        return false;
    }

    public function get_content(): stdClass {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';

        $viewurl = new moodle_url('/blocks/nvq_matrix/view.php');

        $this->content->text =
            html_writer::div(
                html_writer::div(
                    get_string('launcherdesc', 'block_nvq_matrix'),
                    'nvq-launcher-desc'
                ) .
                html_writer::div(
                    html_writer::link(
                        $viewurl,
                        get_string('launcherbutton', 'block_nvq_matrix'),
                        [
                            'class'  => 'btn btn-primary nvq-launcher-btn',
                            'target' => '_blank',
                            'rel'    => 'noopener noreferrer',
                        ]
                    ),
                    'nvq-launcher-action'
                ),
                'nvq-launcher'
            );

        return $this->content;
    }

    /**
     * Parses a descriptor title into sort keys for the uasort() comparator.
     * Used by classes/matrix_data.php via self:: reference within that class.
     * Retained here for any future block-level sorting needs.
     *
     * @param string $title
     * @return array{lo: int, isheader: bool, sub: float}
     */
    public static function extract_lo_sort(string $title): array {
        $title = trim($title);

        if (preg_match('/^LO\s*(\d+)/i', $title, $m)) {
            return [
                'lo'       => (int) $m[1],
                'isheader' => true,
                'sub'      => 0.0,
            ];
        }

        if (preg_match('/^(\d+)\.(\d+)/', $title, $m)) {
            return [
                'lo'       => (int) $m[1],
                'isheader' => false,
                'sub'      => (float) ($m[1] . '.' . $m[2]),
            ];
        }

        return [
            'lo'       => PHP_INT_MAX,
            'isheader' => false,
            'sub'      => 0.0,
        ];
    }
}
