<?php
//include("..\..\sys\\vendor\autoload.php");

// Fix path calculation: from /core/workflow/ we need to go up 3 levels to reach project root
$autoload_path = __DIR__."/../../../sys/vendor/autoload.php";
if (!file_exists($autoload_path)) {
    error_log("Autoload file not found at: " . $autoload_path);
    error_log("Current working directory: " . getcwd());
    error_log("__DIR__ resolves to: " . __DIR__);
    die("Required Finite state machine library could not be loaded. Please check system configuration.");
}

try {
    include($autoload_path);
} catch (Exception $e) {
    error_log("Error loading autoload file: " . $e->getMessage());
    die("Failed to initialize workflow library. Please contact system administrator.");
}

// Verify that the required class is available after include
if (!interface_exists('Finite\StatefulInterface')) {
    error_log("Finite\StatefulInterface not found after loading autoload.php");
    die("Workflow library components missing. Please contact system administrator.");
}

class Document implements Finite\StatefulInterface
{
    private $state;

    public function getFiniteState()
    {
        return $this->state;
    }

    public function setFiniteState($state)
    {
        $this->state = $state;
    }
}

// Create loader as global variable and provide accessor function
$GLOBALS['wf_loader'] = new Finite\Loader\ArrayLoader([
    'class'   => 'Document',
    'states'  => [
        '1' => [
            'type'       => Finite\State\StateInterface::TYPE_INITIAL,
            'properties' => ['deletable' => true, 'editable' => true],
        ],
        '1PRV' => [
            'type'       => Finite\State\StateInterface::TYPE_NORMAL,
            'properties' => ['offline_mode' => true, 'provisional' => true],
        ],
        '2' => [
            'type'       => Finite\State\StateInterface::TYPE_NORMAL,
            'properties' => [],
        ],
		'3A' => [
            'type'       => Finite\State\StateInterface::TYPE_NORMAL,
            'properties' => [],
        ],
		'3B' => [
            'type'       => Finite\State\StateInterface::TYPE_NORMAL,
            'properties' => [],
        ],
		'4A' => [
            'type'       => Finite\State\StateInterface::TYPE_NORMAL,
            'properties' => [],
        ],
		'4B' => [
            'type'       => Finite\State\StateInterface::TYPE_NORMAL,
            'properties' => [],
        ],
        '5' => [
            'type'       => Finite\State\StateInterface::TYPE_FINAL,
            'properties' => ['printable' => true],
        ]
    ],
    'transitions' => [
        'assign' => ['from' => ['1'], 'to' => '2'],
        'engg_approve'  => ['from' => ['2'], 'to' => '3A'],
        'engg_reject'  => ['from' => ['2'], 'to' => '3B'],
		'assign_back_engg_vendor'  => ['from' => ['3B'], 'to' => '2'],
        'assign_back_qa_vendor'  => ['from' => ['4B'], 'to' => '2'],
		//'qa_approve'  => ['from' => ['3A'], 'to' => '4A'],
        'qa_approve'  => ['from' => ['3A'], 'to' => '5'],
        //'qa_reject'  => ['from' => ['3A'], 'to' => '3B'],
        'qa_reject'  => ['from' => ['3A'], 'to' => '4B'],
		//'assign_back_engg'  => ['from' => ['4B'], 'to' => '3B'],
		
		//'engg_approve_final'  => ['from' => ['4A'], 'to' => '5'],
    ],
]);

/**
 * Get the workflow loader instance
 * @return Finite\Loader\ArrayLoader
 */
function getWorkflowLoader() {
    return $GLOBALS['wf_loader'];
}

/*
$document = new Document;
$stateMachine = new Finite\StateMachine\StateMachine($document);
$loader->load($stateMachine);
$stateMachine->initialize();

// Retrieve available transitions
var_dump($stateMachine->getCurrentState()->getTransitions());
// array(1) {
//      [0] => string(7) "assign"
// }

// Check if we can apply the "assign" transition
var_dump($stateMachine->getCurrentState()->can('assign'));
// bool(true)

// Check if we can apply the "accept" transition
var_dump($stateMachine->getCurrentState()->can('accept'));
// bool(false)


// Applying a transition
$stateMachine->apply('assign');
$stateMachine->getCurrentState()->getName();
// string(7) "proposed"
$document->getFiniteState();
// string(7) "proposed"

// Retrieve available transitions
var_dump($stateMachine->getCurrentState()->getTransitions());
// array(1) {
//      [0] => string(7) "assign"
// }

// Check if we can apply the "assign" transition
var_dump($stateMachine->getCurrentState()->can('assign'));
// bool(true)

// Check if we can apply the "accept" transition
var_dump($stateMachine->getCurrentState()->can('accept'));
// bool(false)
*/