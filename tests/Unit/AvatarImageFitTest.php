<?php

/**
 * A profile photo is rendered into a fixed square box. Without an explicit
 * object-fit the browser default is "fill", which stretches anything that is
 * not already square — the photo looks squashed rather than cropped.
 *
 * AvatarImage lives in resources/js/components/ui, which is shadcn-generated
 * and prettier-ignored, so regenerating the component would silently restore
 * the stretching version and nothing else would fail.
 *
 * Plain filesystem calls, no app boot: unit tests do not get the TestCase.
 */
test('the avatar image crops instead of stretching', function () {
    $component = (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/ui/avatar/AvatarImage.vue'
    );

    expect($component)->toContain('object-cover');
});
