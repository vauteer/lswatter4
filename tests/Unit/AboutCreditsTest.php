<?php

/**
 * The credits on the about page are a hand-maintained list, and every entry is
 * rendered with an icon next to it — the project's own mark where it publishes
 * one, a Lucide icon otherwise. A new entry without an icon renders an empty
 * gap, and an untranslated description shows the English fallback on a German
 * page — neither breaks anything loudly enough to be noticed.
 *
 * Plain filesystem calls, no app boot: unit tests do not get the TestCase.
 */
function aboutPage(): string
{
    return (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/js/pages/About.vue'
    );
}

test('every credit on the about page has an icon', function () {
    $page = aboutPage();

    $credits = preg_match_all("/^\s+href: '/m", $page);
    $icons = preg_match_all('/^\s+icon: \w+,$/m', $page);

    expect($icons)->toBe($credits);
});

test('the about page credits the assistant that helped build it', function () {
    expect(aboutPage())->toContain("name: 'Claude Code',");
});

/**
 * The marks are only licensed to point at the projects they belong to, so they
 * have to stay the official artwork rather than becoming redrawn look-alikes.
 */
test('the official brand marks come from simple-icons', function () {
    $page = aboutPage();

    preg_match_all('/^\s+icon: (si\w+),$/m', $page, $matches);

    expect($matches[1])->not->toBeEmpty()
        ->and($page)->toContain("from 'simple-icons'");

    foreach ($matches[1] as $mark) {
        expect($page)->toContain("    {$mark},\n");
    }
});

test('every about page string is translated into German', function () {
    preg_match_all("/trans\(\s*'((?:[^'\\\\]|\\\\.)*)'/s", aboutPage(), $matches);

    $translations = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2).'/lang/de.json'),
        true
    );

    expect($matches[1])->not->toBeEmpty()
        ->and(array_diff($matches[1], array_keys($translations)))->toBeEmpty();
});
