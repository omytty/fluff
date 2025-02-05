<?php

/**
 * Copyright 2019 Alex <blldxt@gmail.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ConstanzeStandard\Fluff\RequestHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The router request handler.
 * 
 * @author Alex <blldxt@gmail.com>
 */
class Handler implements RequestHandlerInterface
{
    /**
     * The single callable handler.
     * 
     * @var callable
     */
    private $handler;

    /**
     * The route url arguments.
     * 
     * @var array
     */
    private $arguments;

    /**
     * Get the `Handler` definition.
     * 
     * @return \Closure
     */
    public static function getDefinition()
    {
        return function($handler, $arguments) {
            return new static($handler, $arguments);
        };
    }

    /**
     * @param callable $handler
     * @param array $arguments
     */
    public function __construct(callable $handler, array $arguments = [])
    {
        $this->handler = $handler;
        $this->arguments = $arguments;
    }

    /**
     * Handles a request and produces a response.
     *
     * Call the single handler to generate the response.
     * 
     * @param callable|array $handler
     * @param array $params
     * 
     * @return RequestHandlerInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return call_user_func($this->handler, $request, $this->arguments);
    }
}
