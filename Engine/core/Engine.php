<?php

namespace Engine\Core;

use Engine\Input\InputGateway;
use Engine\Access\AccessChecker;
use Engine\Collection\Collector;
use Engine\Parsing\Parser;
use Engine\Structure\StructureBuilder;
use Engine\Processing\Normalizer;
use Engine\Validation\Validator;
use Engine\Transfer\TransferBuilder;

class Engine
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function run(array $request): array
    {
        // Input
        $inputGateway = new InputGateway();
        $inputData = $inputGateway->handle($request);

        // Access
        $accessChecker = new AccessChecker();
        $accessData = $accessChecker->check($inputData['target_url']);

        // Collection
        $collector = new Collector();
        $collectionData = $collector->fetch($inputData['target_url']);

        // Parsing
        $parser = new Parser();
        $parsedData = $parser->parse($collectionData['html'] ?? '');

        // Structure
        $structureBuilder = new StructureBuilder();
        $structureData = $structureBuilder->build(
            $parsedData,
            $collectionData['html'] ?? ''
        );

        // Normalization
        $normalizer = new Normalizer();
        $normalizedData = $normalizer->normalize($structureData);

        // Validation
        $validator = new Validator();
        $validationData = $validator->validate($normalizedData);

        // Transfer
$transferBuilder = new TransferBuilder();
$transferData = $transferBuilder->build(
    $normalizedData,
    $validationData
);

        return [
            'status' => 'success',
            'engine' => 'SWCS',
            'mode' => $this->config['engine']['mode'] ?? 'unknown',
            'input' => $inputData,
            'access' => $accessData,
            'collection' => $collectionData,
            'parsed' => $parsedData,
            'structure' => $structureData,
            'normalized' => $normalizedData,
            'validation' => $validationData,
            'transfer' => $transferData,
            'message' => 'Engine Running',
            'request' => $request
        ];
    }
}