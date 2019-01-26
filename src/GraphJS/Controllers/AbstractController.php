<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphJS\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;
use Rakit\Validation\Validator;



/**
 * An abstract controller that includes common operations in GraphJS
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
abstract class AbstractController extends   \Pho\Server\Rest\Controllers\AbstractController
{
    protected $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    protected function succeed(Response $response, array $data = []): void
    {
        $method = $this->getWriteMethod();
        $response->addHeader("Access-Control-Allow-Credentials", "true")->$method(
            array_merge(
                ["success"=>true], 
                $data
            )
        )->end();
    }

    protected function fail(Response $response, string $message = ""): void
    {
        $method = $this->getWriteMethod();
        $response
                    ->addHeader("Access-Control-Allow-Credentials", "true")
                    ->$method([
                        "success" => false,
                        "reason"   => $message
                    ])
                    ->end();
    }
    
    /**
     * Makes sure the method is dependent on session availability
     *
     * @param Request  $request
     * @param Response $response
     * @param Session  $session
     * @param variadic $ignore
     * 
     * @return int 0 if session does not exists, user ID otherwise.
     */
    protected function dependOnSession(Request $request, Response $response, Session $session, ...$ignore): ?string
    {
        $id = $session->get($request, "id");
        if(is_null($id)) {
            $this->fail($response->addHeader("Access-Control-Allow-Credentials", "true"), "No active session");
            return null;
        }
        return $id;
    }

    protected function handleException(Response $response, /*\Exception|\Error*/ $e): void
    {
        $this->fail($response, sprintf(
            "An exception occurred: %s",
            $e->getMessage()
        ));
    }

    public function setExceptionHandler(Response $response): self
    {
        @set_exception_handler(function(/*\Exception|\Error*/ $e) use ($response) {
            $this->handleException($response, $e);
        });
        return $this;
    }

    protected function checkPasswordFormat(string $password): bool
    {
        return preg_match("/[0-9A-Za-z!@#$%_]{5,15}/", $password);
    }

    public function kill() 
    {
        $kernel->doSomethingItCannotDo();
        throw new \Exception("die");
    }
}
