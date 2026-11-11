<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit\Validator;

use Override;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\SvgSanitizer\Validator\SafeSvg;
use Phil\SvgSanitizer\Validator\SafeSvgValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Stringable;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<SafeSvgValidator>
 */
#[CoversClass(SafeSvg::class)]
#[CoversClass(SafeSvgValidator::class)]
final class SafeSvgValidatorTest extends ConstraintValidatorTestCase
{
    private const string MESSAGE = 'This SVG image holds {{ removed }} on line {{ line }}: {{ subject }}.';

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new SafeSvgValidator();
    }

    public function testNullAndEmptyAreValid(): void
    {
        $this->validate(null, new SafeSvg());
        $this->validate('', new SafeSvg());

        $this->assertNoViolation();
    }

    public function testACleanImageIsValid(): void
    {
        $this->validate('<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>', new SafeSvg());

        $this->assertNoViolation();
    }

    public function testEachRemovalIsAViolation(): void
    {
        $this->validate("<svg xmlns=\"http://www.w3.org/2000/svg\">\n<script>alert(1)</script>\n<rect onclick=\"x()\"/></svg>", new SafeSvg());

        $this->buildViolation(self::MESSAGE)
            ->setParameter('{{ removed }}', 'an element that runs code or embeds another document')
            ->setParameter('{{ subject }}', 'script')
            ->setParameter('{{ line }}', '2')
            ->setParameter('{{ reason }}', 'denied_element')
            ->setCode(SafeSvg::NOT_ALLOWED_ERROR)
            ->buildNextViolation(self::MESSAGE)
            ->setParameter('{{ removed }}', 'an event handler')
            ->setParameter('{{ subject }}', 'onclick')
            ->setParameter('{{ line }}', '3')
            ->setParameter('{{ reason }}', 'event_handler')
            ->setCode(SafeSvg::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    /**
     * An image of a few megabytes can hold hundreds of thousands of removals: the first hundred are violations, and
     * one more counts the others.
     */
    public function testTheRemovalsPastTheFirstHundredAreOneViolation(): void
    {
        $this->validate('<svg>'.str_repeat('<g data-x=""/>', 103).'</svg>', new SafeSvg(moreMessage: 'And {{ count }} more.'));

        $violations = $this->context->getViolations();
        self::assertCount(101, $violations);
        self::assertSame('attribute', $violations[99]?->getParameters()['{{ reason }}']);
        $more = $violations[100];
        self::assertNotNull($more);
        self::assertSame('And {{ count }} more.', $more->getMessageTemplate());
        self::assertSame(['{{ count }}' => '3'], $more->getParameters());
        self::assertSame(3, $more->getPlural());
        self::assertSame(SafeSvg::NOT_ALLOWED_ERROR, $more->getCode());
    }

    public function testExactlyAHundredRemovalsAreAHundredViolations(): void
    {
        $this->validate('<svg>'.str_repeat('<g data-x=""/>', 100).'</svg>', new SafeSvg());

        self::assertCount(100, $this->context->getViolations());
    }

    public function testAnImageRefusedWholeIsOneViolation(): void
    {
        $this->validate(new class implements Stringable {
            #[Override]
            public function __toString(): string
            {
                return '<html/>';
            }
        }, new SafeSvg(refusedMessage: 'Refused: {{ reason }}.'));

        $this->buildViolation('Refused: {{ reason }}.')
            ->setParameter('{{ removed }}', 'a document that is not an SVG image')
            ->setParameter('{{ subject }}', 'html')
            ->setParameter('{{ line }}', '1')
            ->setParameter('{{ reason }}', 'not_svg')
            ->setCode(SafeSvg::REFUSED_ERROR)
            ->assertRaised();
    }

    public function testTheSanitizerCarriesThePolicy(): void
    {
        $validator = new SafeSvgValidator(new SvgSanitizer(new Policy()->withAnimations()));
        $validator->validateInContext('<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"><animate attributeName="r" to="8" dur="1s"/></circle></svg>', new SafeSvg(), $this->context);

        $this->assertNoViolation();
    }

    public function testItRefusesOtherConstraints(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('<svg/>', new NotBlank());
    }

    public function testItRefusesValuesThatAreNotStrings(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validate(new stdClass(), new SafeSvg());
    }

    public function testTheConstraintHasDefaults(): void
    {
        $constraint = new SafeSvg(groups: ['strict'], payload: 'p');

        self::assertSame(self::MESSAGE, $constraint->message);
        self::assertSame('This SVG image is refused: {{ removed }}.', $constraint->refusedMessage);
        self::assertSame('This SVG image holds {{ count }} more parts that are not allowed.', $constraint->moreMessage);
        self::assertSame(['strict'], $constraint->groups);
        self::assertSame('p', $constraint->payload);
        self::assertSame('NOT_ALLOWED_ERROR', SafeSvg::getErrorName(SafeSvg::NOT_ALLOWED_ERROR));
        self::assertSame('REFUSED_ERROR', SafeSvg::getErrorName(SafeSvg::REFUSED_ERROR));
    }
}
