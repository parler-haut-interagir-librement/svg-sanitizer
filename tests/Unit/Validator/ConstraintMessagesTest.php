<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit\Validator;

use Phil\SvgSanitizer\RemovalReason;
use Phil\SvgSanitizer\Validator\SafeSvg;
use Phil\SvgSanitizer\Validator\SafeSvgValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The constraint messages through a translator that reads the library's ICU catalogues, as FrameworkBundle does once
 * the application gives it their directory.
 */
#[CoversClass(SafeSvg::class)]
#[CoversClass(SafeSvgValidator::class)]
final class ConstraintMessagesTest extends TestCase
{
    private const string CATALOGUES = __DIR__.'/../../../translations';

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function counts(): iterable
    {
        yield 'en, one more' => ['en', 101, 'This SVG image holds 1 more part that is not allowed.'];
        yield 'en, two more' => ['en', 102, 'This SVG image holds 2 more parts that are not allowed.'];
        yield 'fr, one more' => ['fr', 101, 'Cette image SVG contient 1 autre partie qui n’est pas permise.'];
        yield 'fr, two more' => ['fr', 102, 'Cette image SVG contient 2 autres parties qui ne sont pas permises.'];
    }

    #[DataProvider('counts')]
    public function testTheCountAgreesWithItsNoun(string $locale, int $removals, string $expected): void
    {
        $violations = $this->validator($locale)->validate('<svg>'.str_repeat('<g data-x=""/>', $removals).'</svg>', new SafeSvg());
        $more = $violations[100];

        self::assertNotNull($more);
        self::assertSame($expected, $more->getMessage());
        self::assertSame(['{{ count }}' => $removals - 100], $more->getParameters());
        self::assertSame($removals - 100, $more->getPlural());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function removals(): iterable
    {
        yield 'en' => ['en', 'This SVG image holds an event handler on line 1234: onclick.'];
        yield 'fr' => ['fr', 'Cette image SVG contient un gestionnaire d’événement à la ligne 1234 : onclick.'];
    }

    #[DataProvider('removals')]
    public function testARemovalNamesItsLine(string $locale, string $expected): void
    {
        $violations = $this->validator($locale)->validate('<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat("\n", 1233).'<g onclick="x"/></svg>', new SafeSvg());

        self::assertCount(1, $violations);
        self::assertSame($expected, $violations[0]?->getMessage());
        self::assertSame(1234, $violations[0]->getParameters()['{{ line }}']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'en' => ['en', 'This SVG image is refused: an image that is not well-formed XML.'];
        yield 'fr' => ['fr', 'Cette image SVG est refusée : une image qui n’est pas du XML bien formé.'];
    }

    #[DataProvider('refusals')]
    public function testARefusal(string $locale, string $expected): void
    {
        $violations = $this->validator($locale)->validate('<svg', new SafeSvg());

        self::assertCount(1, $violations);
        self::assertSame($expected, $violations[0]?->getMessage());
        self::assertSame(1, $violations[0]->getParameters()['{{ line }}']);
    }

    public function testTheFrenchCatalogueNamesEveryReason(): void
    {
        $translator = $this->translator('fr');
        $constraint = new SafeSvg();

        foreach ([[$constraint->message, 'Cette image SVG contient ', ' à la ligne 2 : s.'], [$constraint->refusedMessage, 'Cette image SVG est refusée : ', '.']] as [$message, $start, $end]) {
            $phrases = [];

            foreach (RemovalReason::cases() as $reason) {
                $translated = $translator->trans($message, ['{{ removed }}' => $reason->describe(), '{{ line }}' => 2, '{{ subject }}' => 's', '{{ reason }}' => $reason->value], 'validators');

                self::assertStringStartsWith($start, $translated);
                self::assertStringEndsWith($end, $translated);
                self::assertStringNotContainsString($reason->describe(), $translated);
                $phrases[$translated] = true;
            }

            self::assertCount(\count(RemovalReason::cases()), $phrases);
            self::assertStringContainsString('something else', $translator->trans($message, ['{{ removed }}' => 'something else', '{{ line }}' => 2, '{{ subject }}' => 's', '{{ reason }}' => 'other'], 'validators'));
        }
    }

    public function testTheCataloguesTranslateTheDefaultMessages(): void
    {
        $constraint = new SafeSvg();

        $french = require self::CATALOGUES.'/validators+intl-icu.fr.php';
        self::assertIsArray($french);
        self::assertSame([$constraint->message, $constraint->refusedMessage, $constraint->moreMessage], array_keys($french));

        $english = require self::CATALOGUES.'/validators+intl-icu.en.php';
        self::assertIsArray($english);
        self::assertSame([$constraint->moreMessage], array_keys($english));
    }

    public function testWithoutTheCataloguesTheMessagesStayAsWritten(): void
    {
        $violations = Validation::createValidator()->validate('<svg>'.str_repeat('<g data-x=""/>', 102).'</svg>', new SafeSvg());

        self::assertSame('This SVG image holds an attribute that is not allowed on line 1: data-x.', $violations[0]?->getMessage());
        self::assertSame('This SVG image holds 2 more parts that are not allowed.', $violations[100]?->getMessage());
    }

    private function validator(string $locale): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setTranslator($this->translator($locale))
            ->setTranslationDomain('validators')
            ->getValidator();
    }

    private function translator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $catalogue) {
            $translator->addResource('php', self::CATALOGUES.'/validators+intl-icu.'.$catalogue.'.php', $catalogue, 'validators+intl-icu');
        }

        return $translator;
    }
}
