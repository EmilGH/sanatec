<?php

declare(strict_types=1);

test('only languages we actually serve are accepted', function (): void {
    is_same('en', normalize_lang('en'));
    is_same('es', normalize_lang('es'));
    is_same('en', normalize_lang('fr'), 'an unserved language falls back to English');
    is_same('en', normalize_lang(null));
    is_same('en', normalize_lang('../../etc/passwd'), 'path junk must not reach a template');
});

test('language URLs are absolute and stable', function (): void {
    is_same('https://sanatecdiving.com/', lang_url('en', 'https://sanatecdiving.com'));
    is_same('https://sanatecdiving.com/es/', lang_url('es', 'https://sanatecdiving.com'));
    is_same('https://sanatecdiving.com/es/', lang_url('es', 'https://sanatecdiving.com/'),
        'a trailing slash on the base must not double up');
});

test('interface strings translate, and fall back rather than break', function (): void {
    is_same('2 dives', t('th_2_dives', 'en'));
    is_same('2 buceos', t('th_2_dives', 'es'));
    is_same('2 dives', t('th_2_dives', 'fr'), 'an unknown language falls back to English');
    is_same('no_such_key', t('no_such_key', 'en'), 'a missing key returns itself, never empty');
});

test('every interface string exists in both languages', function (): void {
    foreach (ui_strings() as $key => $pair) {
        is_true(isset($pair['en']) && trim($pair['en']) !== '', "missing English for '{$key}'");
        is_true(isset($pair['es']) && trim($pair['es']) !== '', "missing Spanish for '{$key}'");
    }
});
