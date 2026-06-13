<?php

declare(strict_types=1);

use App\Domain\Niu\InvalidNiuException;
use App\Domain\Niu\NiuEntityType;
use App\Domain\Niu\NiuNumber;

describe('validation', function () {
    it('accepts a well-formed personne physique NIU', function () {
        expect(NiuNumber::isValid('P058712345678X'))->toBeTrue();
    });

    it('accepts a well-formed personne morale NIU', function () {
        expect(NiuNumber::isValid('M058712345678X'))->toBeTrue();
    });

    it('rejects an unknown prefix', function () {
        expect(NiuNumber::isValid('X058712345678X'))->toBeFalse();
    });

    it('rejects a NIU that is too short', function () {
        expect(NiuNumber::isValid('P05871234567X'))->toBeFalse();
    });

    it('rejects a NIU that is too long', function () {
        expect(NiuNumber::isValid('P0587123456789X'))->toBeFalse();
    });

    it('rejects letters in the digit block', function () {
        expect(NiuNumber::isValid('P05871234567OX'))->toBeFalse();
    });

    it('rejects a digit in the control position', function () {
        expect(NiuNumber::isValid('P0587123456780'))->toBeFalse();
    });
});

describe('normalization', function () {
    it('uppercases and strips spaces', function () {
        expect(NiuNumber::fromString('p 0587 1234 5678 x')->value)
            ->toBe('P058712345678X');
    });

    it('strips dashes and other separators', function () {
        expect(NiuNumber::fromString('P-0587.1234/5678_X')->value)
            ->toBe('P058712345678X');
    });

    it('drops the "espace en trop" the article warns about', function () {
        expect(NiuNumber::fromString(' P058712345678X ')->value)
            ->toBe('P058712345678X');
    });
});

describe('construction', function () {
    it('throws InvalidNiuException from an invalid string', function () {
        NiuNumber::fromString('not-a-niu');
    })->throws(InvalidNiuException::class);

    it('returns null from tryFrom on invalid input', function () {
        expect(NiuNumber::tryFrom('not-a-niu'))->toBeNull();
    });

    it('builds via tryFrom on valid input', function () {
        expect(NiuNumber::tryFrom('P058712345678X'))->toBeInstanceOf(NiuNumber::class);
    });
});

describe('entity type', function () {
    it('detects a personne physique', function () {
        $niu = NiuNumber::fromString('P058712345678X');
        expect($niu->isPersonnePhysique())->toBeTrue()
            ->and($niu->isPersonneMorale())->toBeFalse()
            ->and($niu->entityType())->toBe(NiuEntityType::PersonnePhysique);
    });

    it('detects a personne morale', function () {
        $niu = NiuNumber::fromString('M058712345678X');
        expect($niu->isPersonneMorale())->toBeTrue()
            ->and($niu->entityType()->label())->toBe('Personne morale');
    });
});

describe('display', function () {
    it('groups the digits for readability', function () {
        expect(NiuNumber::fromString('P058712345678X')->formatted())
            ->toBe('P 0587 1234 5678 X');
    });

    it('casts back to the canonical string', function () {
        expect((string) NiuNumber::fromString('p058712345678x'))
            ->toBe('P058712345678X');
    });

    it('compares by value', function () {
        expect(NiuNumber::fromString('P058712345678X')
            ->equals(NiuNumber::fromString('p-0587-1234-5678-x')))->toBeTrue();
    });
});

describe('typo suggestions', function () {
    it('repairs O→0 and B→8 confusions in the digit block', function () {
        // O typed for the leading 0, B typed for the trailing 8.
        expect(NiuNumber::suggest('PO5871234567BX'))->toBe('P058712345678X');
    });

    it('repairs l/I→1 confusion', function () {
        expect(NiuNumber::suggest('P0587I2345678X'))->toBe('P058712345678X');
    });

    it('repairs a digit typed in the control-letter position', function () {
        // 0 typed where the control letter O belongs.
        expect(NiuNumber::suggest('P0587123456780'))->toBe('P058712345678O');
    });

    it('returns null when the input is already valid', function () {
        expect(NiuNumber::suggest('P058712345678X'))->toBeNull();
    });

    it('returns null when the length is wrong (not a substitution fix)', function () {
        expect(NiuNumber::suggest('PO587BX'))->toBeNull();
    });

    it('returns null when no known substitution repairs it', function () {
        // '#' is not an ambiguous character we correct.
        expect(NiuNumber::suggest('P0587123456#8X'))->toBeNull();
    });
});
