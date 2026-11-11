<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Internal;

use Closure;
use Phil\CssSanitizer\AtRuleKind;
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\Policy as CssPolicy;
use Phil\CssSanitizer\Sanitized as CssSanitized;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Sheet\GroupRule;
use Phil\CssSanitizer\Sheet\StyleRule;
use Phil\CssSanitizer\Sheet\StyleSheet;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\FunctionValue;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;
use Phil\CssSanitizer\Url\RefuseUrls;

/**
 * The text of a `<style>` element as a style sheet held inside its image: the CSS sanitizer places every rule under
 * the root's `id`, its scope, and the ids the selectors name are written as the image's own are.
 *
 * A selector that starts with the root's `id`, written by the image itself, as a diagram writes `#diagram .node`,
 * would then match nothing under the scope: it loses the scope the sanitizer wrote, since it already names the root
 * or what lies inside it, as long as it holds no `+` nor `~`, which reach siblings, and, when it names the root
 * itself, its rule holds no nested rule, whose `&` would stand for the root and reach its siblings.
 *
 * @internal
 */
final readonly class ScopedStyleSheet
{
    private Serializer $serializer;

    /**
     * @param Closure(string): string $id writes an `id` as the image's own are kept
     */
    public function __construct(
        private CssPolicy $policy,
        private Closure $id,
    ) {
        $this->serializer = new Serializer();
    }

    /**
     * The style sheet kept under the root whose `id` is `$scope`, as CSS, and what the CSS sanitizer removed.
     *
     * @return array{string, CssSanitized}
     */
    public function sanitize(string $css, string $scope): array
    {
        $policy = $this->policy
            ->withoutAtRules(AtRuleKind::Import, AtRuleKind::Page, AtRuleKind::FontFace)
            ->withUrlRewriter(new RefuseUrls())
            ->withScope('#'.Serializer::identifier($scope));
        $sanitized = new CssSanitizer($policy, $this->serializer)->sanitize($css);

        return [$this->serializer->styleSheet(new StyleSheet($this->rules($sanitized->sheet->rules, $scope, true))), $sanitized];
    }

    /**
     * @param list<StyleRule|GroupRule> $rules
     *
     * @return list<StyleRule|GroupRule>
     */
    private function rules(array $rules, string $scope, bool $scoped): array
    {
        $kept = [];

        foreach ($rules as $rule) {
            $kept[] = $rule instanceof StyleRule
                ? new StyleRule(
                    array_map(fn (array $selector): array => $scoped ? $this->scoped($selector, $scope, [] !== $rule->rules) : $this->prefixed($selector), $rule->selectors),
                    $rule->declarations,
                    $this->rules($rule->rules, $scope, false),
                )
                : new GroupRule($rule->name, $rule->prelude, $rule->declarations, $this->rules($rule->rules, $scope, $scoped));
        }

        return $kept;
    }

    /**
     * A selector the CSS sanitizer placed under the scope — its hash, then a space —, its ids rewritten, and the scope
     * left out when the rest already names the root or what lies inside it.
     *
     * @param list<ComponentValue> $selector
     *
     * @return list<ComponentValue>
     */
    private function scoped(array $selector, string $scope, bool $nested): array
    {
        $rest = $this->prefixed(\array_slice($selector, 2));

        return $this->namesTheRoot($rest, $scope, $nested) ? $rest : [...\array_slice($selector, 0, 2), ...$rest];
    }

    /**
     * Whether a selector starts with the root's `id` and reaches nothing but the root and what lies inside it.
     *
     * @param list<ComponentValue> $selector
     */
    private function namesTheRoot(array $selector, string $scope, bool $nested): bool
    {
        $first = $selector[0] ?? null;

        if (!$first instanceof Token || !$first->is(TokenType::Hash) || $scope !== $first->value) {
            return false;
        }

        $descends = false;

        foreach ($selector as $value) {
            if ($value instanceof Token && ($value->is(TokenType::Delim, '+') || $value->is(TokenType::Delim, '~'))) {
                return false;
            }

            $descends = $descends || ($value instanceof Token && ($value->is(TokenType::Whitespace) || $value->is(TokenType::Delim, '>')));
        }

        return $descends || !$nested;
    }

    /**
     * Component values with every `id` they name written as the image's own are, inside functions too. The blocks of a
     * selector are attribute selectors, which name no id.
     *
     * @param list<ComponentValue> $values
     *
     * @return list<ComponentValue>
     */
    private function prefixed(array $values): array
    {
        return array_map(fn (ComponentValue $value): ComponentValue => match (true) {
            $value instanceof Token && $value->isId => new Token(TokenType::Hash, ($this->id)($value->value), $value->sourceLine, isId: true),
            $value instanceof FunctionValue => new FunctionValue($value->name, $this->prefixed($value->arguments), $value->sourceLine),
            default => $value,
        }, $values);
    }
}
