<?php

/**
 * Test for issue 373: "Suggestion: Allow for a cleaner way to selectively shortcut loggings"
 * https://github.com/bonny/WordPress-Simple-History/issues/373.
 * 
 * Tests that the core loggers can be disabled.
 * 
 * Run with Docker Compose:
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run functional:test_issue_373`
 */
class Issue373Cest {
	public function test_issue_373( FunctionalTester $I ) {        
        // Log a 404 error to the 404 test logger,
        // so we have something in the db/log.
        $I->amOnPage('index.php?p=404');
        $I->makeHtmlSnapshot('404-page');
        $I->seeResponseCodeIs(404);

        $I->loginAsAdmin();
        $I->amOnPluginsPage();   
        $I->activatePlugin('issue-373-disable-core-loggers');

        $I->amGoingTo('See if any loggers are active on the debug tab');
        $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug&selected-tab=debug');
        $I->dontSee('There has been a critical error on this website.');       
        $I->see('Listing 2 loggers');
        $I->makeHtmlSnapshot('debug-tab');

        // Check that main feed works.
        $I->amGoingTo('Check that the main history feed works');
        // Go to simple history page
        $I->amOnPage('/wp-admin/index.php?page=simple_history_page');
        // $I->see('Simple History');
        $I->amOnAdminAjaxPage([
            "action" => 'simple_history_api',
            'type' => 'overview',
            'format' => 'html',
            'posts_per_page' => 30,
            'paged' => '1',
            'dates' => 'lastdays:30'
        ]);
        $I->seeResponseCodeIsSuccessful();
        $I->makeHtmlSnapshot('ajax-feed.json');
        // admin-ajax.php?action=simple_history_api&type=overview&format=html&posts_per_page=30&paged=1&dates=lastdays%3A30

    }
}
