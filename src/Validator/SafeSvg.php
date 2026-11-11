<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value, a string, is an SVG image the sanitizer keeps whole: nothing in it is removed and it is not refused.
 * Each removal is a violation, whose parameters name what was removed, why, and on which line; past the first
 * `Sanitized::MAX_REMOVALS`, one more violation, `moreMessage`, counts the others. An image refused whole is one
 * violation. `null` and the empty string are valid, as for every constraint but `NotBlank`.
 *
 * The policy is the one of the `SvgSanitizer` the validator is built with — the default policy, unless the
 * application registers `SafeSvgValidator` as a service with its own sanitizer.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class SafeSvg extends Constraint
{
    public const string NOT_ALLOWED_ERROR = '3e9a51c7-0b2d-4f86-9c14-d7a65e28b0f1';

    public const string REFUSED_ERROR = 'a84d0f2b-6c17-4e39-8b52-1f9e3c7a06d4';

    protected const array ERROR_NAMES = [
        self::NOT_ALLOWED_ERROR => 'NOT_ALLOWED_ERROR',
        self::REFUSED_ERROR => 'REFUSED_ERROR',
    ];

    /**
     * @param ?list<string> $groups
     */
    public function __construct(
        public string $message = 'This SVG image holds {{ removed }} on line {{ line }}: {{ subject }}.',
        public string $refusedMessage = 'This SVG image is refused: {{ removed }}.',
        public string $moreMessage = 'This SVG image holds {{ count }} more parts that are not allowed.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
