<?php

namespace Adrenallen\AiAgentsLaravel\Agents;


use Adrenallen\AiAgentsLaravel\ChatModels\ChatModelResponse;

/**
 * BaseAgent
 * Responsible for defining the responsibility of an "agent"
 * also includes a list of functions with descriptive docblocks that define what each function is for
 * 
 * This class also includes the base functions to use reflection to pull in the "allowed" functions that are sent
 * to underlying chat model
 */
class BaseAgent {

    public $chatModel;

    function __construct($chatModel) {
        $this->chatModel = $chatModel;

        // Set the model to have this agents functions now
        $this->chatModel->setFunctions($this->getAgentFunctions());
    }

    // A desription of what this agent is responsible for
    // this is fed to the chat model to give it context on what to do
    public function getAgentDuty(): string {
        return "You are a helpful generalist assistant.";
    }

    public function ask($message) : string {
        return $this->parseModelResponse($this->chatModel->sendUserMessage($message));
    }

    private function parseModelResponse(ChatModelResponse $response) : string {
        if ($response->error){
            throw new \Exception($response->error);
        }

        if ($response->functionCall){
            $functionCall = $response->functionCall;
            $functionName = $functionCall['name'];
            $functionArgs = $functionCall['arguments'];
            
            $functionResult = "";
            try {
                $functionResult = call_user_func_array([$this, $functionName], (array)json_decode($functionArgs));
            } catch (\Throwable $e) {
                $functionResult = "An error occurred while running the function " 
                    . $functionName 
                    . ":'" . str($e) . "'. You may need to ask the user for more information.";
            }

            //print_r((array)json_decode($functionArgs));
            return $this->parseModelResponse(
                $this->chatModel->sendFunctionResult(
                    $functionName,
                    $functionResult
                )
            );
        }

        return $response->message;
    }
    

    /**
     * getAgentFunctions
     *
     * Returns a list of functions that the agent is allowed to use
     * These are passed into the chat model so it knows what it is capable of doing
     * 
     * @return array
     */
    public function getAgentFunctions(): array {
        $reflector = new \ReflectionClass($this);
        $methods = $reflector->getMethods(\ReflectionMethod::IS_PUBLIC);
        $allowedFunctions = [];
        foreach ($methods as $method) {
            if (AgentFunction::isMethodForAgent($method)){
                $allowedFunctions[] = AgentFunction::createFromMethodReflection($method);
            }
            
        }
        return $allowedFunctions;
    }

}