<?php
namespace Psalm\Tests\LanguageServer;

use LanguageServerProtocol\Position;
use Psalm\Context;
use Psalm\Internal\Analyzer\FileAnalyzer;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Internal\Provider\Providers;
use Psalm\Tests\Internal\Provider;
use Psalm\Tests\TestConfig;

class CompletionTest extends \Psalm\Tests\TestCase
{
    /**
     * @return void
     */
    public function setUp() : void
    {
        parent::setUp();

        FileAnalyzer::clearCache();

        $this->file_provider = new \Psalm\Tests\Internal\Provider\FakeFileProvider();

        $config = new TestConfig();

        $providers = new Providers(
            $this->file_provider,
            new \Psalm\Tests\Internal\Provider\ParserInstanceCacheProvider(),
            null,
            null,
            new Provider\FakeFileReferenceCacheProvider()
        );

        $this->project_analyzer = new ProjectAnalyzer(
            $config,
            $providers,
            false,
            true,
            ProjectAnalyzer::TYPE_CONSOLE,
            1,
            false
        );
        $this->project_analyzer->setPhpVersion('7.3');
        $this->project_analyzer->getCodebase()->store_node_types = true;
    }

    /**
     * @return void
     */
    public function testCompletionOnThisWithNoAssignment()
    {
        $codebase = $this->project_analyzer->getCodebase();
        $config = $codebase->config;
        $config->throw_exception = false;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace B;

                class A {
                    /** @var int|null */
                    protected $a;

                    public function foo() {
                        $this->
                    }
                }'
        );

        $codebase->file_provider->openFile('somefile.php');
        $codebase->scanFiles();
        $this->analyzeFile('somefile.php', new Context());

        $this->assertSame(['B\A', '->'], $codebase->getCompletionDataAtPosition('somefile.php', new Position(8, 31)));
    }

    /**
     * @return void
     */
    public function testCompletionOnThisWithAssignmentBelow()
    {
        $codebase = $this->project_analyzer->getCodebase();
        $config = $codebase->config;
        $config->throw_exception = false;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace B;

                class A {
                    /** @var int|null */
                    protected $a;

                    public function foo() : self {
                        $this->

                        $a = "foo";
                    }
                }'
        );

        $codebase->file_provider->openFile('somefile.php');
        $codebase->scanFiles();
        $this->analyzeFile('somefile.php', new Context());

        $this->assertSame(['B\A', '->'], $codebase->getCompletionDataAtPosition('somefile.php', new Position(8, 31)));
    }

    /**
     * @return void
     */
    public function testCompletionOnThisWithIfBelow()
    {
        $codebase = $this->project_analyzer->getCodebase();
        $config = $codebase->config;
        $config->throw_exception = false;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace B;

                class A {
                    /** @var int|null */
                    protected $a;

                    public function foo() : self {
                        $this

                        if(rand(0, 1)) {}
                    }
                }'
        );

        $codebase->file_provider->openFile('somefile.php');
        $codebase->scanFiles();
        $this->analyzeFile('somefile.php', new Context());

        $codebase->addTemporaryFileChanges(
            'somefile.php',
            '<?php
                namespace B;

                class A {
                    /** @var int|null */
                    protected $a;

                    public function foo() : self {
                        $this->

                        if(rand(0, 1)) {}
                    }
                }'
        );
        $codebase->reloadFiles($this->project_analyzer, ['somefile.php']);
        $codebase->analyzer->analyzeFiles($this->project_analyzer, 1, false);

        $this->assertSame(['B\A', '->'], $codebase->getCompletionDataAtPosition('somefile.php', new Position(8, 31)));
    }

    /**
     * @return void
     */
    public function testCompletionOnThisProperty()
    {
        $codebase = $this->project_analyzer->getCodebase();
        $config = $codebase->config;
        $config->throw_exception = false;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace B;

                class C {
                    public function otherFunction() : void
                }

                class A {
                    /** @var C */
                    protected $cee_me;

                    public function __construct() {
                        $this->cee_me = new C();
                    }

                    public function foo() : void {
                        $this->cee_me->
                    }
                }'
        );

        $codebase = $this->project_analyzer->getCodebase();

        $codebase->file_provider->openFile('somefile.php');
        $codebase->scanFiles();
        $this->analyzeFile('somefile.php', new Context());

        $this->assertSame(['B\C', '->'], $codebase->getCompletionDataAtPosition('somefile.php', new Position(16, 39)));
    }

    /**
     * @return void
     */
    public function testCompletionOnMethodReturnValue()
    {
        $codebase = $this->project_analyzer->getCodebase();
        $config = $codebase->config;
        $config->throw_exception = false;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace B;

                class A {
                    public function foo() : self {
                        return $this;
                    }
                }
                
                function (A $a) {
                    $a->foo()->
                }
                '
        );

        $codebase->file_provider->openFile('somefile.php');
        $codebase->scanFiles();
        $this->analyzeFile('somefile.php', new Context());

        $this->assertSame(['B\A', '->'], $codebase->getCompletionDataAtPosition('somefile.php', new Position(10, 31)));
    }
}
