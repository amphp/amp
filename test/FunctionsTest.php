<?php

namespace Amp\Test;

use Amp\NativeReactor;
use Amp\Success;
use Amp\Failure;
use Amp\Deferred;
use Amp\PromiseStream;
use function Amp\all;
use function Amp\any;
use function Amp\some;
use function Amp\resolve;

class FunctionsTest extends \PHPUnit_Framework_TestCase {

    public function testAllResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        all($promises)->when(function($e, $r) {
            list($a, $b, $c, $d) = $r;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testSomeResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        some($promises)->when(function($e, $r) {
            list($errors, $results) = $r;
            list($a, $b, $c, $d) = $results;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testAnyResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        any($promises)->when(function($e, $r) {
            list($errors, $results) = $r;
            list($a, $b, $c, $d) = $results;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testAllResolvesWithArrayOfResults() {
        all(['r1' => 42, 'r2' => new Success(41)])->when(function($error, $result) {
            $expected = ['r1' => 42, 'r2' => 41];
            $this->assertSame($expected, $result);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage zanzibar
     */
    public function testAllThrowsIfAnyIndividualPromiseFails() {
        $exception = new \RuntimeException('zanzibar');
        all([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function($error) {
            throw $error;
        });
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        some([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function($error, $result) use ($exception) {
            list($errors, $results) = yield some($promises);
            $this->assertSame(['r2' => $exception], $errors);
            $this->assertSame(['r1' => 42, 'r3' => 40], $results);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        some([
            'r1' => new Failure(new \RuntimeException),
            'r2' => new Failure(new \RuntimeException),
        ])->when(function($error) {
            throw $error;
        });
    }

    public function testResolutionFailuresAreThrownIntoGenerator() {
        $foo = function() {
            $a = yield new Success(21);
            $b = 1;
            try {
                yield new Failure(new \Exception('test'));
                $this->fail('Code path should not be reached');
            } catch (\Exception $e) {
                $this->assertSame('test', $e->getMessage());
                $b = 2;
            }

            return ($a * $b);
        };

        (new NativeReactor)->run(function($reactor) use ($foo) {
            $result = yield resolve($foo(), $reactor);
            $this->assertSame(42, $result);
        });
    }

    public function testBadGeneratorYieldError() {
        $constraint = new \StdClass;
        $constraint->invocationCount = 0;
        $constraint->exception = null;

        $reactor = new NativeReactor;
        $reactor->onError(function(\Exception $error) use ($constraint) {
            $constraint->invocationCount++;
            $constraint->exception = $error;
        });

        ($reactor)->run(function() {
            $result = yield from (function() { yield; yield 42; return; })();
        });

        $this->assertSame(1, $constraint->invocationCount);
        $this->assertInstanceOf("DomainException", $constraint->exception);

        $expected = "Unexpected Generator yield (Promise|null expected); %s yielded at key %s on line %s in %s";
        $actual = $constraint->exception->getMessage();
        $this->assertTrue($this->matchesPrintfString($expected, $actual));
    }

    private function matchesPrintfString(string $pattern, string $subject) {
        $pattern = preg_quote($pattern, "/");
        $pattern = str_replace("%d", "[0-9]+", $pattern);
        $pattern = str_replace("%s", ".+", $pattern);
        $pattern = "/{$pattern}/";

        return (preg_match($pattern, $subject) === 1);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsResolverPromise() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                yield;
                throw new \Exception('When in the chronicle of wasted time');
                yield;
            };

            yield resolve($gen(), $reactor);
        });
    }

    public function testAllCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            list($a, $b) = yield all([
                    new Success(21),
                    new Success(2),
            ]);

            $result = ($a * $b);
            $this->assertSame(42, $result);
        });
    }

    public function testAllCombinatorResolutionWithNonPromises() {
        (new NativeReactor)->run(function($reactor) {
            list($a, $b, $c) = yield all([new Success(21), new Success(2), 10]);
            $result = ($a * $b * $c);
            $this->assertSame(420, $result);
        });
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testAllCombinatorResolutionThrowsIfAnyOnePromiseFails() {
        (new NativeReactor)->run(function($reactor) {
            list($a, $b) = yield all([
                new Success(21),
                new Failure(new \Exception('When in the chronicle of wasted time')),
            ]);
        });
    }

    public function testExplicitAllCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            list($a, $b, $c) = yield all([
                new Success(21),
                new Success(2),
                10
            ]);

            $this->assertSame(420, ($a * $b * $c));
        });
    }

    public function testExplicitAnyCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            list($errors, $results) = yield any([
                'a' => new Success(21),
                'b' => new Failure(new \Exception('test')),
            ]);
            $this->assertSame('test', $errors['b']->getMessage());
            $this->assertSame(21, $results['a']);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testExplicitSomeCombinatorResolutionFailsOnError() {
        (new NativeReactor)->run(function($reactor) {
            yield some([
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ]);
        });
    }

    public function testCoroutineReturnValue() {
        $co = function() {
            yield;
            yield "return" => 42;
            yield;
        };
        (new NativeReactor)->run(function($reactor) use ($co) {
            $result = (yield \Amp\resolve($co()));
            $this->assertSame(42, $result);
        });
    }

    public function testCoroutineResolutionBuffersYieldedPromiseStream() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new Deferred;
            $reactor->repeat(function($reactor, $watcherId) use (&$i, $promisor) {
                $i++;
                $promisor->update($i);
                if ($i === 3) {
                    $reactor->cancel($watcherId);
                    $promisor->succeed();
                }
            }, 10);

            $result = (yield new PromiseStream($promisor->promise()));
            $this->assertSame([1, 2, 3], $result);
        });
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage test
     */
    public function testCoroutineResolutionThrowsOnPromiseStreamBufferFailure() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new Deferred;
            $reactor->repeat(function($reactor, $watcherId) use (&$i, $promisor) {
                $promisor->fail(new \Exception("test"));
            }, 10);

            $result = (yield new PromiseStream($promisor->promise()));
        });
    }
}
