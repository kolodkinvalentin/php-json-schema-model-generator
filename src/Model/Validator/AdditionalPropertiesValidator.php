<?php

declare(strict_types = 1);

namespace PHPModelGenerator\Model\Validator;

use PHPModelGenerator\Exception\Object\InvalidAdditionalPropertiesException;
use PHPModelGenerator\Exception\SchemaException;
use PHPModelGenerator\Model\Property\Property;
use PHPModelGenerator\Model\Property\PropertyInterface;
use PHPModelGenerator\Model\Schema;
use PHPModelGenerator\Model\SchemaDefinition\JsonSchema;
use PHPModelGenerator\PropertyProcessor\PropertyMetaDataCollection;
use PHPModelGenerator\PropertyProcessor\PropertyFactory;
use PHPModelGenerator\PropertyProcessor\PropertyProcessorFactory;
use PHPModelGenerator\SchemaProcessor\SchemaProcessor;
use PHPModelGenerator\Utils\RenderHelper;

/**
 * Class AdditionalPropertiesValidator
 *
 * @package PHPModelGenerator\Model\Validator
 */
class AdditionalPropertiesValidator extends PropertyTemplateValidator
{
    protected const PROPERTY_NAME = 'additional property';

    protected const PROPERTIES_KEY = 'properties';
    protected const ADDITIONAL_PROPERTIES_KEY = 'additionalProperties';

    protected const EXCEPTION_CLASS = InvalidAdditionalPropertiesException::class;

    private PropertyInterface $validationProperty;
    private bool $collectAdditionalProperties = false;

    /**
     * AdditionalPropertiesValidator constructor.
     *
     * @throws SchemaException
     */
    public function __construct(
        SchemaProcessor $schemaProcessor,
        Schema $schema,
        JsonSchema $propertiesStructure,
        ?string $propertyName = null,
    ) {
        $propertyFactory = new PropertyFactory(new PropertyProcessorFactory());

        $this->validationProperty = $propertyFactory->create(
            new PropertyMetaDataCollection([static::PROPERTY_NAME]),
            $schemaProcessor,
            $schema,
            static::PROPERTY_NAME,
            $propertiesStructure->withJson($propertiesStructure->getJson()[static::ADDITIONAL_PROPERTIES_KEY]),
        );

        $this->validationProperty->onResolve(function (): void {
            $this->resolve();
        });

        $patternProperties = array_keys($schema->getJsonSchema()->getJson()['patternProperties'] ?? []);

        parent::__construct(
            new Property($propertyName ?? $schema->getClassName(), null, $propertiesStructure),
            DIRECTORY_SEPARATOR . 'Validator' . DIRECTORY_SEPARATOR . 'AdditionalProperties.phptpl',
            [
                'schema' => $schema,
                'validationProperty' => $this->validationProperty,
                'additionalProperties' => RenderHelper::varExportArray(
                    array_keys($propertiesStructure->getJson()[static::PROPERTIES_KEY] ?? []),
                ),
                'patternProperties' => $patternProperties ? RenderHelper::varExportArray($patternProperties) : null,
                'generatorConfiguration' => $schemaProcessor->getGeneratorConfiguration(),
                'viewHelper' => new RenderHelper($schemaProcessor->getGeneratorConfiguration()),
                // by default don't collect additional property data
                'collectAdditionalProperties' => &$this->collectAdditionalProperties,
            ],
            static::EXCEPTION_CLASS,
            ['&$invalidProperties'],
        );
    }

    /**
     * @inheritDoc
     */
    public function getCheck(): string
    {
        $this->removeRequiredPropertyValidator($this->validationProperty);

        return parent::getCheck();
    }

    public function setCollectAdditionalProperties(bool $collectAdditionalProperties): void
    {
        $this->collectAdditionalProperties = $collectAdditionalProperties;
    }

    public function getValidationProperty(): PropertyInterface
    {
        return $this->validationProperty;
    }

    /**
     * Initialize all variables which are required to execute a property names validator
     */
    public function getValidatorSetUp(): string
    {
        return '
            $properties = $value;
            $invalidProperties = [];
        ';
    }
}
