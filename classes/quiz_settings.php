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

/**
 * Entity model representing quiz settings for the seb plugin.
 *
 * @package    quizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_seb;

use CFPropertyList\CFArray;
use CFPropertyList\CFBoolean;
use CFPropertyList\CFDictionary;
use CFPropertyList\CFNumber;
use CFPropertyList\CFString;
use core\persistent;
use lang_string;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class quiz_settings extends persistent {

    /** Table name for the persistent. */
    const TABLE = 'quizaccess_seb_quizsettings';

    /** @var property_list $plist The SEB config represented as a Property List object. */
    private $plist;

    /**
     * Create an instance of this class.
     *
     * @param int $id If set, this is the id of an existing record, used to load the data.
     * @param \stdClass $record If set will be passed to {@link self::from_record()}.
     */
    public function __construct($id = 0, \stdClass $record = null) {
        parent::__construct($id, $record);
        // Get existing config.
        $config = $this->get('config');
        // Parse basic settings into a property list.
        $this->plist = new property_list($config);
    }

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'quizid' => [
                'type' => PARAM_INT,
            ],
            'cmid' => [
                'type' => PARAM_INT,
            ],
            'templateid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'requiresafeexambrowser' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'sebconfigfile' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'showsebtaskbar' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'showwificontrol' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'showreloadbutton' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'showtime' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'showkeyboardlayout' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'allowuserquitseb' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'quitpassword' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'linkquitseb' => [
                'type' => PARAM_URL,
                'default' => '',
            ],
            'userconfirmquit' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'enableaudiocontrol' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'muteonstartup' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'allowspellchecking' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'allowreloadinexam' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'activateurlfiltering' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'filterembeddedcontent' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'expressionsallowed' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'regexallowed' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'expressionsblocked' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'regexblocked' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'suppresssebdownloadlink' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'allowedbrowserexamkeys' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'configkey' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
            'config' => [
                'type' => PARAM_RAW,
                'default' => '',
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Validate the browser exam keys string.
     *
     * @param string $keys Newline separated browser exam keys.
     * @return true|lang_string If there is an error, an error string is returned.
     */
    protected function validate_allowedbrowserexamkeys($keys) {
        $keys = $this->split_keys($keys);
        foreach ($keys as $i => $key) {
            if (!preg_match('~^[a-f0-9]{64}$~', $key)) {
                return new lang_string('allowedbrowserkeyssyntax', 'quizaccess_seb');
            }
        }
        if (count($keys) != count(array_unique($keys))) {
            return new lang_string('allowedbrowserkeysdistinct', 'quizaccess_seb');
        }
        return true;
    }

    /**
     * Get the browser exam keys as a pre-split array instead of just as a string.
     *
     * @return array
     */
    protected function get_allowedbrowserexamkeys() : array {
        $keysstring = $this->raw_get('allowedbrowserexamkeys');
        $keysstring = empty($keysstring) ? '' : $keysstring;
        return $this->split_keys($keysstring);
    }

    /**
     * Hook to execute before an update.
     *
     * Please note that at this stage the data has already been validated and therefore
     * any new data being set will not be validated before it is sent to the database.
     */
    protected function before_update() {
        $this->before_save();
    }

    /**
     * Hook to execute before a create.
     *
     * Please note that at this stage the data has already been validated and therefore
     * any new data being set will not be validated before it is sent to the database.
     */
    protected function before_create() {
        $this->before_save();
    }

    /**
     * As there is no hook for before both create and update, this function is called by both hooks.
     */
    private function before_save() {
        // Recalculate config and config key.
        $this->compute_config();
        $this->compute_config_key();
    }

    /**
     * Generate the config key from the config string.
     */
    private function compute_config_key() {
        $config = $this->get('config');
        $configkey = config_key::generate($config)->get_hash();
        $this->set('configkey', $configkey);
    }

    /**
     * Create or update the config string based on the current quiz settings.
     */
    private function compute_config() {
        switch ($this->get('requiresafeexambrowser')) {
            case settings_provider::USE_SEB_NO:
                break;

            case settings_provider::USE_SEB_TEMPLATE:
                $this->process_template_settings();
                break;

            case settings_provider::USE_SEB_UPLOAD_CONFIG:
                $this->process_seb_config_file();
                break;

            default:
                // Remaining case settings_provider::USE_SEB_CONFIG_MANUALLY.
                $this->process_default_case();
        }

        // Export and save the config, ready for DB.
        $this->set('config', $this->plist->to_xml());
    }

    /**
     * Creates an initial plist, then applies all settings associated with the quiz.
     */
    private function process_default_case() {
        // If at any point a configuration file has been uploaded and parsed, clear the settings.
        $this->plist = new property_list();
        $this->progress_default_standard_settings();
    }

    /**
     * Creates an initial plist from the template uploaded, then applies all settings associated with the quiz.
     */
    private function process_template_settings() {
        $template = template::get_record(['id' => $this->get('templateid')]);
        $this->plist = new property_list($template->get('content'));

        // Process quit settings.
        $this->process_quit_settings();

        // Add the sensible default options to the configuration and exported SEB files.
        $this->process_required_enforced_settings();
    }

    /**
     * This function processes the elements that have been set via the modform fields.
     *
     * It's called during case USE_SEB_TEMPLATE and USE_SEB_CONFIG_MANUALLY.
     */
    private function progress_default_standard_settings() {
        // Process all settings that are boolean.
        $this->process_bool_settings();

        // Process quit settings.
        $this->process_quit_settings();

        // Add all the URL filters.
        $this->process_url_filters();

        // Add the sensible default options to the configuration and exported SEB files.
        $this->process_required_enforced_settings();
    }

    /**
     * Sets or updates some sensible default settings, these are the items 'startURL' and 'sendBrowserExamKey'.
     */
    private function process_required_enforced_settings() {
        global $CFG;

        $quizurl = new moodle_url($CFG->wwwroot . "/mod/quiz/view.php", ['id' => $this->get('cmid')]);

        $starturl = $this->plist->get_element_value('startURL');
        if ($starturl) {
            $this->plist->update_element_value('startURL', $quizurl->out(true));
        } else {
            $this->plist->add_element_to_root('startURL', new CFString($quizurl->out(true)));
        }

        $sendbrowserexamkey = $this->plist->get_element_value('sendBrowserExamKey');
        if ($sendbrowserexamkey) {
            $this->plist->update_element_value('sendBrowserExamKey', true);
        } else {
            $this->plist->add_element_to_root('sendBrowserExamKey', new CFBoolean(true));
        }
    }

    /**
     * If file is uploaded, save the file to the config field.
     * This is processed after the validation step, so a SEB file should exist at this point.
     */
    private function process_seb_config_file() {
        $file = settings_provider::get_module_context_sebconfig_file($this->get('cmid'));

        // If file has been uploaded, overwrite existing config.
        if (!empty($file)) {
            $this->plist = new property_list($file->get_content());
        }

        // Process quit settings.
        $this->process_quit_settings();

        // Add the sensible default options to the configuration and exported SEB files.
        $this->process_required_enforced_settings();
    }

    /**
     * Use the boolean map to add Moodle boolean setting to config PList.
     */
    private function process_bool_settings() {
        $settings = $this->to_record();
        $map = $this->get_bool_seb_setting_map();
        foreach ($settings as $setting => $value) {
            if (isset($map[$setting])) {
                $enabled = $value == 1 ? true : false;
                $this->plist->add_element_to_root($map[$setting], new CFBoolean($enabled));
            }
        }
    }

    /**
     * Turn hashed quit password and quit link into PList strings and add to config PList.
     */
    private function process_quit_settings() {
        $settings = $this->to_record();
        if (!empty($settings->quitpassword) && is_string($settings->quitpassword)) {
            // Hash quit password.
            $hashedpassword = hash('SHA256', $settings->quitpassword);
            $this->plist->add_element_to_root('hashedQuitPassword', new CFString($hashedpassword));
        }

        if (!empty($settings->linkquitseb) && is_string($settings->linkquitseb)) {
            $this->plist->add_element_to_root('quitURL', new CFString($settings->linkquitseb));
        }

        // Does the plist (template or config file) have an existing quitURL?
        $quiturl = $this->plist->get_element_value('quitURL');
        if (!empty($quiturl)) {
            $this->set('linkquitseb', $quiturl);
        }
    }

    /**
     * Turn return separated strings for URL filters into a PList array and add to config PList.
     */
    private function process_url_filters() {
        $settings = $this->to_record();
        // Create rules to each expression provided and add to config.
        $urlfilterrules = [];
        // Get all rules separated by newlines and remove empty rules.
        $expallowed = array_filter(explode(PHP_EOL, $settings->expressionsallowed));
        $expblocked = array_filter(explode(PHP_EOL, $settings->expressionsblocked));
        $regallowed = array_filter(explode(PHP_EOL, $settings->regexallowed));
        $regblocked = array_filter(explode(PHP_EOL, $settings->regexblocked));
        foreach ($expallowed as $rulestring) {
            $urlfilterrules[] = $this->create_filter_rule($rulestring, true, false);
        }
        foreach ($expblocked as $rulestring) {
            $urlfilterrules[] = $this->create_filter_rule($rulestring, false, false);
        }
        foreach ($regallowed as $rulestring) {
            $urlfilterrules[] = $this->create_filter_rule($rulestring, true, true);
        }
        foreach ($regblocked as $rulestring) {
            $urlfilterrules[] = $this->create_filter_rule($rulestring, false, true);
        }
        $this->plist->add_element_to_root('URLFilterRules', new CFArray($urlfilterrules));
    }

    /**
     * Create a CFDictionary represeting a URL filter rule.
     *
     * @param bool $allowed Allowed or blocked.
     * @param bool $isregex Regex or simple.
     * @param string $rulestring The expression to filter with.
     * @return CFDictionary A PList dictionary.
     */
    private function create_filter_rule(string $rulestring, bool $allowed, bool $isregex) : CFDictionary {
        $action = $allowed ? 1 : 0;
        return new CFDictionary([
                    'action' => new CFNumber($action),
                    'active' => new CFBoolean(true),
                    'expression' => new CFString($rulestring),
                    'regex' => new CFBoolean($isregex),
                    ]);
    }

    /**
     * Map the settings that are booleans to the Safe Exam Browser config keys.
     *
     * @return array Moodle setting as key, SEB setting as value.
     */
    private function get_bool_seb_setting_map() : array {
        return [
            'activateurlfiltering' => 'URLFilterEnable',
            'allowspellchecking' => 'allowSpellCheck',
            'allowreloadinexam' => 'browserWindowAllowReload',
            'allowuserquitseb' => 'allowQuit',
            'enableaudiocontrol' => 'audioControlEnabled',
            'filterembeddedcontent' => 'URLFilterEnableContentFilter',
            'muteonstartup' => 'audioMute',
            'showkeyboardlayout' => 'showInputLanguage',
            'showreloadbutton' => 'showReloadButton',
            'showsebtaskbar' => 'showTaskBar',
            'showtime' => 'showTime',
            'showwificontrol' => 'allowWlan',
            'userconfirmquit' => 'quitURLConfirm',
        ];
    }

    /**
     * This helper method takes list of browser exam keys in a string and splits it into an array of separate keys.
     *
     * @param string $keys the allowed keys.
     * @return array of string, the separate keys.
     */
    private function split_keys(string $keys) : array {
        $keys = preg_split('~[ \t\n\r,;]+~', $keys, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($keys as $i => $key) {
            $keys[$i] = strtolower($key);
        }
        return $keys;
    }
}
