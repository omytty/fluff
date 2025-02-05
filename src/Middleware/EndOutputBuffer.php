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

namespace ConstanzeStandard\Fluff\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Close the output buffer.
 * 
 * @author Alex <blldxt@gmail.com>
 */
class EndOutputBuffer implements MiddlewareInterface
{
    /**
     * The chunk size of response.
     * 
     * @var int
     */
    private $chunkSize;

    /**
     * Flush or clean output buffers.
     * 
     * @param bool $isFlush
     */
    private static function endOutputBuffers($isFlush)
    {
        if ($isFlush && \function_exists('fastcgi_finish_request')) {
            return fastcgi_finish_request();
        }

        $status = ob_get_status(true);
        $level = \count($status);
        $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($isFlush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);
        while ($level > 0) {
            $level--;
            $s = $status[$level];
            if ((isset($s['del']) ? $s['del'] : !isset($s['flags']) || ($s['flags'] & $flags) === $flags)) {
                if ($isFlush) {
                    ob_end_flush();
                } else {
                    ob_end_clean();
                }
            }
        }
    }

    /**
     * @param int $chunkSize
     * @param bool $flush
     */
    public function __construct(int $chunkSize = 4096)
    {
        $this->chunkSize = $chunkSize;
    }

    /**
     * Process an incoming server request.
     * 
     * Close the output buffer, and flush if it's `HEAD` http request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $this->respondHeader($response);
        $isHead = strcasecmp($request->getMethod(), 'HEAD') === 0;
        $this->respond($response, !$isHead);
        return $response;
    }

    /**
     * Emit the response, flush or clean the output buffer.
     * 
     * @param ResponseInterface $response the PSR-7 response.
     */
    public function respond(ResponseInterface $response, $flush = true)
    {
        if ($flush) {
            $outputHandle = fopen('php://output', 'a');
            foreach ($this->respondContents($response) as $partOfContent) {
                fwrite($outputHandle, $partOfContent);
                if (ob_get_level() > 0) {
                    flush();
                    ob_flush();
                }
            }
            fclose($outputHandle);
        }
        static::endOutputBuffers($flush);
    }

    /**
     * Send accept header from response.
     * 
     * @param ResponseInterface $response
     */
    private function respondHeader(ResponseInterface $response)
    {
        if (!headers_sent()) {
            $version = $response->getProtocolVersion();
            $statusCode = $response->getStatusCode();
            $reasonPhrase = $response->getReasonPhrase();
            header(sprintf('HTTP/%s %s %s', $version, $statusCode, $reasonPhrase));

            foreach ($response->getHeaders() as $key => $headers) {
                $replace = 0 === strcasecmp($key, 'content-type');
                foreach ($headers as $header) {
                    header($key . ': ' . $header, $replace);
                }
            }
        }
    }

    /**
     * Get accept contents iterable.
     * 
     * @param ResponseInterface $response
     * 
     * @return iterable
     */
    private function respondContents(ResponseInterface $response): iterable
    {
        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contentLength  = $response->getHeaderLine('Content-Length');
        if (!$contentLength) {
            $contentLength = $body->getSize();
        }

        while ($contentLength > 0 && !$body->eof()) {
            $length = min($this->chunkSize, (int)$contentLength);
            $contentLength -= $length;
            yield $body->read($length);
        }
    }
}
