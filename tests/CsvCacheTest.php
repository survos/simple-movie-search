<?php

namespace App\Tests;

use App\Service\CsvDatabase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

class CsvCacheTest extends KernelTestCase
{
    /**
     * @dataProvider csvSteps
     */
    public function testSomething(array $test): void
    {
        $kernel = self::bootKernel();
        $csvDatabase = new CsvDatabase($test['db'], $test['key'], $test['headers'] ?? []);
        $csvDatabase->flushFile(); // purge?  reset? We need to start with a clean file.
        $csvDatabase->purge();
        foreach ($test['steps'] as $step) {
            $key = $step['key'];
            $data = $step['data'] ?? [];
            $expects = $step['expects'] ?? null;
            $csv = $step['csv'] ?? null;
            $actual = match ($operation = $step['operation']) {
                'has' => $csvDatabase->has($key),
                'get' => $csvDatabase->get($key),
                'delete' => $csvDatabase->delete($key),
                'set' => $csvDatabase->set($key, (array)$data),
                default =>
                assert(false, "Operation not supported " . $operation)
            };
            if (!is_null($expects)) {
                $this->assertSame($expects, $actual);
            }
            if (!is_null($csv)) {
                $this->assertSame($csv, file_get_contents($csvDatabase->getFilename()));
            }
        }
//        $this->assertSame($test,  [], json_encode($test));

        $this->assertSame('test', $kernel->getEnvironment());
        // $routerService = static::getContainer()->get('router');
        // $myCustomService = static::getContainer()->get(CustomService::class);
    }

    public function csvSteps()
    {
        $data = Yaml::parseFile(__DIR__ . '/test.yaml');
        foreach ($data['cache'] as $test) {
            yield [$test['db'] => $test];
        }
    }

}
