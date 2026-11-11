<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Validator;

use Override;
use Phil\SvgSanitizer\SvgSanitizer;
use Stringable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates `SafeSvg`: sanitizes the value and turns each removal it lists into a violation, and those it only counts
 * into one more, or the refusal of the whole image into one.
 */
final class SafeSvgValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SvgSanitizer $sanitizer = new SvgSanitizer(),
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeSvg) {
            throw new UnexpectedTypeException($constraint, SafeSvg::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $sanitized = $this->sanitizer->sanitize((string) $value);
        $refused = $sanitized->isRefused();

        foreach ($sanitized->removals as $removal) {
            $this->context->buildViolation($refused ? $constraint->refusedMessage : $constraint->message)
                ->setParameter('{{ removed }}', $removal->reason->describe())
                ->setParameter('{{ subject }}', $removal->subject)
                ->setParameter('{{ line }}', (string) $removal->line)
                ->setParameter('{{ reason }}', $removal->reason->value)
                ->setCode($refused ? SafeSvg::REFUSED_ERROR : SafeSvg::NOT_ALLOWED_ERROR)
                ->addViolation();
        }

        if ($sanitized->omittedRemovals > 0) {
            $this->context->buildViolation($constraint->moreMessage)
                ->setParameter('{{ count }}', (string) $sanitized->omittedRemovals)
                ->setPlural($sanitized->omittedRemovals)
                ->setCode(SafeSvg::NOT_ALLOWED_ERROR)
                ->addViolation();
        }
    }
}
