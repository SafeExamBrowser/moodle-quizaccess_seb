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
 * PHPUnit tests for plugin rule class.
 *
 * @package    quizaccess_seb
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quizaccess_seb\quiz_settings;
use quizaccess_seb\settings_provider;
use quizaccess_seb\tests\phpunit\quizaccess_seb_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . "/mod/quiz/accessrule/seb/rule.php"); // Include plugin rule class.
require_once($CFG->dirroot . "/mod/quiz/mod_form.php"); // Include plugin rule class.
require_once(__DIR__ . '/base.php');

class quizaccess_seb_rule_testcase extends quizaccess_seb_testcase {

    /**
     * Ran before every test.
     */
    public function setUp() {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test no errors are found with valid data.
     */
    public function test_validate_settings_with_valid_data() {
        $form = $this->createMock('mod_quiz_mod_form');
        // Validate settings with a dummy form.
        $errors = quizaccess_seb::validate_settings_form_fields([], ['instance' => 1], [],
            $form);
        $this->assertEmpty($errors);
    }

    /**
     * Test errors are found with invalid data.
     */
    public function test_validate_settings_with_invalid_data() {
        $form = $this->createMock('mod_quiz_mod_form');
        // Validate settings with a dummy form and quiz instance.
        $errors = quizaccess_seb::validate_settings_form_fields([],
                ['instance' => 1, 'seb_requiresafeexambrowser' => 'Uh oh!'], [], $form);
        $this->assertEquals(['seb_requiresafeexambrowser' => 'Data submitted is invalid'], $errors);
    }

    /**
     * Test settings are saved to DB.
     */
    public function test_save_settings() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Using a generator will create the quiz_settings record.
        // Lets remove it to emulate an existing quiz prior to installing the plugin.
        $DB->delete_records(quiz_settings::TABLE, ['quizid' => $quiz->id]);

        $this->assertFalse($DB->record_exists('quizaccess_seb_quizsettings', ['quizid' => $quiz->id]));
        quizaccess_seb::save_settings($quiz);
        $this->assertNotFalse($DB->record_exists('quizaccess_seb_quizsettings', ['quizid' => $quiz->id]));
    }

    /**
     * Test nothing happens when deleted is called without settings saved.
     */
    public function test_delete_settings_without_existing_settings() {
        global $DB;
        $quiz = new stdClass();
        $quiz->id = 1;
        quizaccess_seb::delete_settings($quiz);
        $this->assertFalse($DB->record_exists('quizaccess_seb_quizsettings', ['quizid' => $quiz->id]));
    }

    /**
     * Test settings are deleted from DB.
     */
    public function test_delete_settings_with_existing_settings() {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Using a generator will create the quiz_settings record.
        $this->assertNotFalse($DB->record_exists('quizaccess_seb_quizsettings', ['quizid' => $quiz->id]));
        quizaccess_seb::delete_settings($quiz);
        $this->assertFalse($DB->record_exists('quizaccess_seb_quizsettings', ['quizid' => $quiz->id]));
    }

    /**
     * Test access prevented if access keys are invalid.
     */
    public function test_access_prevented_if_access_keys_invalid() {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb.
        $quizsetting = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsetting->set('requiresafeexambrowser', settings_provider::USE_SEB_CONFIG_MANUALLY);
        $quizsetting->save();

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        // Check that correct error message is returned.
        $errormsg = $rule->prevent_access();
        $this->assertNotEmpty($errormsg);
        $this->assertContains("The config key or browser exam keys could not be validated. "
                . "Please ensure you are using the Safe Exam Browser with correct configuration file.", $errormsg);
        $this->assertContains("https://safeexambrowser.org/download_en.html", $errormsg);
        $this->assertContains("sebs://www.example.com/moodle/mod/quiz/accessrule/seb/config.php", $errormsg);
        $this->assertContains("https://www.example.com/moodle/mod/quiz/accessrule/seb/config.php", $errormsg);
    }

    /**
     * Test access not prevented if config key matches header.
     */
    public function test_access_allowed_if_config_key_valid() {
        global $FULLME;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb.
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CONFIG_MANUALLY);
        $quizsettings->save();

        $configkey = $quizsettings->get('configkey');

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/quiz/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $configkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH'] = $expectedhash;

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        // Check that correct error message is returned.
        $this->assertFalse($rule->prevent_access());
    }

    /**
     * Test access not prevented if browser exam keys match headers.
     */
    public function test_access_allowed_if_browser_exam_keys_valid() {
        global $FULLME;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb and save BEK.
        $browserexamkey = hash('sha256', 'testkey');
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $quizsettings->set('allowedbrowserexamkeys', $browserexamkey);
        $quizsettings->save();

        // Set up dummy request.
        $FULLME = 'https://example.com/moodle/mod/quiz/attempt.php?attemptid=123&page=4';
        $expectedhash = hash('sha256', $FULLME . $browserexamkey);
        $_SERVER['HTTP_X_SAFEEXAMBROWSER_REQUESTHASH'] = $expectedhash;

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        // Check that correct error message is returned.
        $this->assertFalse($rule->prevent_access());
    }

    /**
     * Test access allowed if using client configuration and SEB user agent header is valid.
     */
    public function test_access_allowed_if_using_client_config_basic_header_is_valid() {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb.
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $quizsettings->save();

        // Set up basic dummy request.
        $_SERVER['HTTP_USER_AGENT'] = 'SEB_TEST_SITE';

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        // Check that correct error message is returned.
        $this->assertFalse($rule->prevent_access());
    }

    /**
     * Test access prevented if using client configuration and SEB user agent header is invalid.
     */
    public function test_access_prevented_if_using_client_configuration_and_basic_head_is_invalid() {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb.
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CLIENT_CONFIG); // Doesn't check config key.
        $quizsettings->save();

        // Set up basic dummy request.
        $_SERVER['HTTP_USER_AGENT'] = 'WRONG_TEST_SITE';

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        // Check that correct error message is returned.
        $this->assertContains('This quiz has been configured to use the Safe Exam Browser with client configuration.',
            $rule->prevent_access());
    }

    /**
     * Test access not prevented if SEB not required.
     */
    public function test_access_allowed_if_seb_not_required() {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to not require seb.
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_NO);
        $quizsettings->save();

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);

        // The rule will not exist as the settings are not configured for SEB usage.
        $this->assertNull($rule);
    }

    /**
     * Test access not prevented if USER has bypass capability.
     */
    public function test_access_allowed_if_user_has_bypass_capability() {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb.
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CONFIG_MANUALLY);
        $quizsettings->save();

        // Set the bypass SEB check capability to $USER.
        $this->assign_user_capability('quizaccess/seb:bypassseb', context_module::instance($quiz->cmid)->id);

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        // Check that correct error message is returned.
        $this->assertFalse($rule->prevent_access());
    }

    /**
     * Test that quiz form cannot be saved if the global settings are set to require a password and no password is set.
     */
    public function test_mod_quiz_form_cannot_be_saved_if_global_settings_force_quiz_password_and_none_is_set() {
        // Set global settings to require quiz password but set password to be empty.
        set_config('quizpasswordrequired', '1', 'quizaccess_seb');

        $form = $this->createMock('mod_quiz_mod_form');
        // Validate settings with a dummy form.
        $errors = quizaccess_seb::validate_settings_form_fields([], ['instance' => 1], [], $form);
        $this->assertContains(get_string('passwordnotset', 'quizaccess_seb'), $errors);
    }

    /**
     * Test that access to quiz is allowed if global setting is set to restrict quiz if no quiz password is set, and global quiz
     * password is set.
     */
    public function test_mod_quiz_form_can_be_saved_if_global_settings_force_quiz_password_and_is_set() {
        // Set global settings to require quiz password but set password to be empty.
        set_config('quizpasswordrequired', '1', 'quizaccess_seb');

        $form = $this->createMock('mod_quiz_mod_form');
        // Validate settings with a dummy form.
        $errors = quizaccess_seb::validate_settings_form_fields([], ['instance' => 1, 'quizpassword' => 'set'], [], $form);
        $this->assertNotContains(get_string('passwordnotset', 'quizaccess_seb'), $errors);
    }

    /**
     * Test get_download_button_only, checks for empty config setting quizaccess_seb/downloadlink.
     */
    public function test_get_download_button_only() {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Set quiz setting to require seb.
        $quizsettings = quiz_settings::get_record(['quizid' => $quiz->id]);
        $quizsettings->set('requiresafeexambrowser', settings_provider::USE_SEB_CONFIG_MANUALLY);
        $quizsettings->save();

        $rule = quizaccess_seb::make(new quiz($quiz, get_coursemodule_from_id('quiz', $quiz->cmid), $course), 0, true);
        $reflection = new \ReflectionClass('quizaccess_seb');
        $method = $reflection->getMethod('get_download_button_only');
        $method->setAccessible(true);

        // The current default contents.
        $this->assertContains('https://safeexambrowser.org/download_en.html', $method->invoke($rule));

        set_config('downloadlink', '', 'quizaccess_seb');

        // Will not return any button if the URL is empty.
        $this->assertSame('', $method->invoke($rule));
    }

}
