<?php

declare(strict_types=1);

/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link http://phpdoc.org
 */

namespace phpDocumentor\Reflection\DocBlock\Tags;

use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\DocBlock\DescriptionFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\Context as TypeContext;
use phpDocumentor\Reflection\Types\Mixed_;
use phpDocumentor\Reflection\Types\Void_;
use Webmozart\Assert\Assert;

use function array_keys;
use function explode;
use function implode;
use function is_string;
use function preg_match;
use function sort;
use function strpos;
use function substr;
use function trim;
use function var_export;

/**
 * Reflection class for an {@}method in a Docblock.
 */
final class Method extends BaseTag implements Factory\StaticMethod
{
    /** @var string */
    protected $name = 'method';

    /** @var string */
    private $methodName;

    /**
     * @phpstan-var array<int, array{name: string, type: Type}>
     * @var array<int, array<string, Type|string>>
     */
    private $arguments;

    /** @var bool */
    private $isStatic;

    /** @var Type */
    private $returnType;

    /** @var Param[] */
    private $parameters;

    /**
     * @param array<int, array<string, Type|string>> $arguments
     * @phpstan-param array<int, array{name: string, type: Type}|string> $arguments
     */
    public function __construct(
        string $methodName,
        array $arguments = [],
        ?Type $returnType = null,
        bool $static = false,
        ?Description $description = null,
        array $parameters = []
    ) {
        Assert::stringNotEmpty($methodName);

        if ($returnType === null) {
            $returnType = new Void_();
        }

        $this->methodName  = $methodName;
        $this->arguments   = $this->filterArguments($arguments);
        $this->returnType  = $returnType;
        $this->isStatic    = $static;
        $this->description = $description;
        $this->parameters = $this->fromLegacyArguments($parameters, $this->arguments);
    }

    public static function create(
        string $body,
        ?TypeResolver $typeResolver = null,
        ?DescriptionFactory $descriptionFactory = null,
        ?TypeContext $context = null
    ): ?self {
        Assert::stringNotEmpty($body);
        Assert::notNull($typeResolver);
        Assert::notNull($descriptionFactory);

        // 1. none or more whitespace
        // 2. optionally the keyword "static" followed by whitespace
        // 3. optionally a word with underscores followed by whitespace : as
        //    type for the return value
        // 4. then optionally a word with underscores followed by () and
        //    whitespace : as method name as used by phpDocumentor
        // 5. then a word with underscores, followed by ( and any character
        //    until a ) and whitespace : as method name with signature
        // 6. any remaining text : as description
        if (
            !preg_match(
                '/^
                # Static keyword
                # Declares a static method ONLY if type is also present
                (?:
                    (static)
                    \s+
                )?
                # Return type
                (?:
                    (
                        (?:[\w\|_\\\\]*\$this[\w\|_\\\\]*)
                        |
                        (?:
                            (?:[\w\|_\\\\]+)
                            # array notation
                            (?:\[\])*
                        )*+
                    )
                    \s+
                )?
                # Method name
                ([\w_]+)
                # Arguments
                (?:
                    \(([^\)]*)\)
                )?
                \s*
                # Description
                (.*)
            $/sux',
                $body,
                $matches
            )
        ) {
            return null;
        }

        [, $static, $returnType, $methodName, $argumentLines, $description] = $matches;

        $static = $static === 'static';

        if ($returnType === '') {
            $returnType = 'void';
        }

        $returnType  = $typeResolver->resolve($returnType, $context);
        $description = $descriptionFactory->create($description, $context);

        $parameters = [];
        if ($argumentLines !== '') {
            $argumentsExploded = explode(',', $argumentLines);
            foreach ($argumentsExploded as $argument) {
                $parameters[] = Param::create(trim($argument), $typeResolver, $descriptionFactory, $context);
            }
        }

        return new static($methodName, self::toLegacyArguments($parameters), $returnType, $static, $description, $parameters);
    }

    /** @return array<int, array{name: string, type: Type}> */
    private static function toLegacyArguments(array $parameters): array
    {
        return array_map(
            static function (Param $param): array {
                return [
                    'name' => $param->getVariableName(),
                    'type' => $param->getType()
                ];
            },
            $parameters
        );
    }

    /**
     * Retrieves the method name.
     */
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @deprecated arguments are a limited way to express method arguments, use {@see self::getParameters} to have full
     *  featured method parameters. This method will be removed in v6.0
     * @return array<int, array<string, Type|string>>
     * @phpstan-return array<int, array{name: string, type: Type}>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /** @return Param[] */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Checks whether the method tag describes a static method or not.
     *
     * @return bool TRUE if the method declaration is for a static method, FALSE otherwise.
     */
    public function isStatic(): bool
    {
        return $this->isStatic;
    }

    public function getReturnType(): Type
    {
        return $this->returnType;
    }

    public function __toString(): string
    {
        $arguments = [];
        foreach ($this->parameters as $parameter) {
            $arguments[] = ($parameter->getType() ?? new Mixed_()) . ' ' .
                ($parameter->isReference() ? '&' : '') .
                ($parameter->isVariadic() ? '...' : '') .
                '$' . $parameter->getVariableName();
        }

        $argumentStr = '(' . implode(', ', $arguments) . ')';

        if ($this->description) {
            $description = $this->description->render();
        } else {
            $description = '';
        }

        $static = $this->isStatic ? 'static' : '';

        $returnType = (string) $this->returnType;

        $methodName = $this->methodName;

        return $static
            . ($returnType !== '' ? ($static !== '' ? ' ' : '') . $returnType : '')
            . ($methodName !== '' ? ($static !== '' || $returnType !== '' ? ' ' : '') . $methodName : '')
            . $argumentStr
            . ($description !== '' ? ' ' . $description : '');
    }

    /**
     * @param mixed[][]|string[] $arguments
     * @phpstan-param array<int, array{name: string, type: Type}|string> $arguments
     *
     * @return mixed[][]
     * @phpstan-return array<int, array{name: string, type: Type}>
     */
    private function filterArguments(array $arguments = []): array
    {
        $result = [];
        foreach ($arguments as $argument) {
            if (is_string($argument)) {
                $argument = ['name' => $argument];
            }

            if (!isset($argument['type'])) {
                $argument['type'] = new Mixed_();
            }

            $keys = array_keys($argument);
            sort($keys);
            if ($keys !== ['name', 'type']) {
                throw new InvalidArgumentException(
                    'Arguments can only have the "name" and "type" fields, found: ' . var_export($keys, true)
                );
            }

            $result[] = $argument;
        }

        return $result;
    }

    private static function stripRestArg(string $argument): string
    {
        if (strpos($argument, '...') === 0) {
            $argument = trim(substr($argument, 3));
        }

        return $argument;
    }

    private function fromLegacyArguments(array $parameters, array $arguments) : array
    {
        if (!empty($parameters)) {
            return $parameters;
        }

        return array_map(
            static function ($argument) {
                return new Param($argument['name'], $argument['type']);
            },
            $arguments
        );
    }
}
