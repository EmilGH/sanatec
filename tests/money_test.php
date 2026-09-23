<?php

declare(strict_types=1);

/** Prices are the thing customers argue about, so formatting gets tested. */

test('whole pesos format without decimals', function (): void {
    is_same('$10,000', money(10000), 'the printed guide shows $10,000');
    is_same('$2,400', money('2400.00'));
    is_same('$50,000', money(50000.0));
});

test('centavos survive if a price ever has them', function (): void {
    is_same('$1,234.50', money(1234.5));
});

test('no price is null, not zero', function (): void {
    is_same(null, money(null), 'Divemaster has no price and must not render as $0');
    is_same(null, money(''));
    is_same('$0', money(0), 'an explicit zero is still a price');
});

test('prices typed by a human are understood', function (): void {
    is_same(10000.0, parse_money('$10,000'));
    is_same(10000.0, parse_money('10000'));
    is_same(10000.0, parse_money('10 000'));
    is_same(4200.0, parse_money(' $4,200 '));
});

test('an empty price field means no price', function (): void {
    is_same(null, parse_money(''));
    is_same(null, parse_money(null));
    is_same(null, parse_money('—'), 'the dash placeholder must not become 0');
    is_same(null, parse_money('ask'));
});

test('a price round-trips through the form', function (): void {
    is_same('$3,900', money(parse_money('$3,900')));
});
