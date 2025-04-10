<?php

declare(strict_types=1);

namespace Spiral\Validation\Laravel\Tests\Functional;

use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Filters\Exception\ValidationException;
use Spiral\Filters\Model\Filter;
use Spiral\Testing\Attribute\TestScope;
use Spiral\Validation\Laravel\Tests\App\Filters\CreatePostFilter;
use Spiral\Validation\Laravel\Tests\App\Filters\FilterWithArrayMapping;
use Spiral\Validation\Laravel\Tests\App\Filters\SimpleFilter;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

final class ValidationTest extends TestCase
{
    /**
     * @param class-string<Filter> $filterClass
     * @dataProvider requestsSuccessProvider
     */
    #[TestScope(['http', 'http-request'])]
    public function testValidationSuccess(string $filterClass, array $data, bool $withFile = false): void
    {
        $this->getContainer()->bind(ServerRequestInterface::class, $this->createRequest($data, $withFile));

        $filter = $this->getContainer()->get($filterClass)->getData();

        if ($withFile) {
            $this->assertInstanceOf(SymfonyUploadedFile::class, $filter['image']);
            unset($filter['image']);
        }

        $this->assertSame($data, $filter);
    }

    /**
     * @param class-string<Filter> $filterClass
     * @dataProvider requestsErrorProvider
     */
    #[TestScope(['http', 'http-request'])]
    public function testValidationError(string $filterClass, array $data): void
    {
        $this->getContainer()->bind(ServerRequestInterface::class, $this->createRequest($data));

        $this->expectException(ValidationException::class);
        $this->getContainer()->get($filterClass);
    }

    public function requestsSuccessProvider(): \Traversable
    {
        yield [SimpleFilter::class, ['username' => 'foo', 'email' => 'foo@gmail.com']];
        yield [FilterWithArrayMapping::class, ['username' => 'foo', 'email' => 'foo@gmail.com'], true];
        yield [CreatePostFilter::class, ['title' => 'New post', 'slug' => 'new-post', 'sort' => 1], true];
    }

    public function requestsErrorProvider(): \Traversable
    {
        yield [SimpleFilter::class, ['email' => 'foo@gmail.com']];
        yield [SimpleFilter::class, ['username' => 'foo']];
        yield [SimpleFilter::class, ['username' => 'foo', 'email' => 'foo']];

        yield [FilterWithArrayMapping::class, ['email' => 'foo@gmail.com']];
        yield [FilterWithArrayMapping::class, ['username' => 'foo']];
        yield [FilterWithArrayMapping::class, ['username' => 'foo', 'email' => 'foo']];

        yield [CreatePostFilter::class, ['title' => 'New post', 'slug' => 'new-post', 'sort' => -1]];
        yield [CreatePostFilter::class, ['title' => 'New post', 'slug' => 'new-post']];
        yield [CreatePostFilter::class, ['title' => 'foo', 'slug' => 'foo', 'sort' => 1]];
    }

    private function createRequest(array $data, bool $withFile = false): ServerRequestInterface
    {
        $factory = $this->getContainer()->get(ServerRequestFactoryInterface::class);

        $request = $factory->createServerRequest('POST', '/foo')->withParsedBody($data);

        if ($withFile) {
            $path = \dirname(__DIR__, 2) . '/app/fixtures/sample-1.jpg';

            $request = $request->withUploadedFiles([
                'image' => new UploadedFile(
                    \fopen($path, 'rb'),
                    \filesize($path),
                    0,
                    $path
                )
            ]);
        }

        return $request;
    }
}
