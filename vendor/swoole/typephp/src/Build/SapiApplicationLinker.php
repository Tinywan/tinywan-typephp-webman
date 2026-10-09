<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

final class SapiApplicationLinker
{
    /** @param \Closure(string):void $output */
    public function __construct(
        private readonly string $phpSourceDirectory,
        private readonly string $phpBuildDirectory,
        private readonly string $phpxArchive,
        /** @var array<string, string> */
        private readonly array $sapiArchives,
        private readonly string $buildDirectory,
        private readonly array $targets,
        private readonly ?string $entryFile,
        private readonly \Closure $output,
    ) {
    }

    /**
     * @param list<string> $projectObjects
     * @param array<string, string> $outputs
     */
    public function link(array $projectObjects, array $outputs): string
    {
        $makefile = $this->phpBuildDirectory . '/Makefile';
        if (!is_file($makefile)) {
            throw new \RuntimeException("Private PHP Makefile does not exist: {$makefile}");
        }
        $variables = $this->readMakeVariables($makefile, [
            'PHP_CLI_OBJS', 'PHP_FPM_OBJS', 'EXTRA_LIBS', 'CXX',
        ]);
        $cxx = trim($variables['CXX'] ?? '') ?: 'c++';
        $binaryObjects = implode(' ', [...$projectObjects, $this->phpxArchive]);
        $extraLibraries = trim(($variables['EXTRA_LIBS'] ?? '') . ' -lgmp -lgmpxx -lmpfr');

        $cliObjects = $variables['PHP_CLI_OBJS'] ?? '';
        $fpmObjects = $variables['PHP_FPM_OBJS'] ?? '';
        $adapterMakefile = $this->generateSapiAdapters($cliObjects, $fpmObjects);
        $adapterDirectory = $this->adapterDirectory();
        if (in_array('cli', $this->targets, true)) {
            $cliObjects = $this->replaceObject(
                $cliObjects,
                'sapi/cli/php_cli.lo',
                $adapterDirectory . '/php_cli.lo',
            );
            $cliObjects = $this->replaceObject(
                $cliObjects,
                'sapi/cli/php_cli_server.lo',
                $adapterDirectory . '/php_cli_server.lo',
            );
        }
        if (in_array('fpm', $this->targets, true)) {
            $fpmObjects = $this->replaceObject(
                $fpmObjects,
                'sapi/fpm/fpm/fpm_main.lo',
                $adapterDirectory . '/fpm_main.lo',
            );
        }
        $this->run([
            'make', '-f', $adapterMakefile, '-j1',
            ...array_map(
                fn (string $target): string => 'typephp-' . $target . '-adapter',
                $this->targets,
            ),
        ], $this->phpBuildDirectory);

        foreach ($this->targets as $target) {
            $output = $outputs[$target] ?? throw new \RuntimeException("Missing {$target} output path");
            $linkOutput = $this->absoluteOutputPath($output);
            $runtimeArchive = $this->sapiArchives[$target]
                ?? throw new \RuntimeException("Missing cached PHP {$target} runtime archive");
            $this->mkdir(dirname($linkOutput));
            $arguments = [
                'make', '-j1',
                'CC=' . $cxx,
                'CXX=' . $cxx,
                'PHP_BINARY_OBJS=' . $binaryObjects,
                'PHP_GLOBAL_OBJS=',
                'EXTRA_LIBS=' . $extraLibraries,
            ];
            if ($target === 'cli') {
                $arguments[] = 'PHP_CLI_OBJS=' . $this->onlyEntryObjects(
                    $cliObjects,
                    ['php_cli.lo', 'php_cli_server.lo'],
                )
                    . ' ' . $runtimeArchive;
                $arguments[] = 'SAPI_CLI_PATH=' . $linkOutput;
                $arguments[] = 'cli';
            } else {
                $arguments[] = 'PHP_FPM_OBJS=' . $this->onlyEntryObjects($fpmObjects, ['fpm_main.lo'])
                    // The shared generated object also contains the Embed-only
                    // process-title function table. FPM does not register that
                    // table, but the linker must still resolve its callbacks.
                    . ' ' . $this->onlyEntryObjects(
                        $cliObjects,
                        ['ps_title.lo', 'php_cli_process_title.lo'],
                    )
                    . ' ' . $runtimeArchive;
                $arguments[] = 'SAPI_FPM_PATH=' . $linkOutput;
                $arguments[] = 'fpm';
            }
            ($this->output)('Linking self-contained PHP ' . strtoupper($target) . ': ' . $output);
            $this->run($arguments, $this->phpBuildDirectory);
            if (!is_executable($linkOutput)) {
                throw new \RuntimeException("PHP {$target} output was not generated: {$output}");
            }
        }
        return $outputs['cli'] ?? $outputs['fpm'];
    }

    private function absoluteOutputPath(string $output): string
    {
        if ($output === '') {
            throw new \RuntimeException('SAPI output path cannot be empty');
        }
        if ($output[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $output) === 1) {
            return $output;
        }
        $directory = getcwd();
        if ($directory === false) {
            throw new \RuntimeException('Cannot resolve the SAPI application output directory');
        }
        return $directory . DIRECTORY_SEPARATOR . $output;
    }

    private function generateSapiAdapters(string $cliObjects, string $fpmObjects): string
    {
        $directory = $this->adapterDirectory();
        $this->mkdir($directory);
        $targets = [];
        $rules = [];
        if (in_array('cli', $this->targets, true)) {
            if ($this->entryFile === null) {
                throw new \RuntimeException('The CLI SAPI target requires an entry file');
            }
            $source = $directory . '/php_cli.c';
            $object = $directory . '/php_cli.lo';
            $serverSource = $directory . '/php_cli_server.c';
            $serverObject = $directory . '/php_cli_server.lo';
            $code = $this->readSource('/sapi/cli/php_cli.c');
            $needle = "static zend_result cli_seek_file_begin(zend_file_handle *file_handle, char *script_file)\n{\n";
            $replacement = $needle
                . "\tif (typephp_embedded_file_exists(script_file)) {\n"
                . "\t\tzend_stream_init_filename(file_handle, script_file);\n"
                . "\t\tfile_handle->primary_script = 1;\n"
                . "\t\treturn SUCCESS;\n\t}\n";
            $code = $this->patchOnce($code, $needle, $replacement, 'CLI script opener');
            $code = $this->patchCliArguments($code);
            $code = $this->insertDeclaration($code, true);
            $this->writeIfChanged($source, $code);
            $serverCode = $this->readSource('/sapi/cli/php_cli_server.c');
            $statCall = 'php_sys_stat(buf, &sb)';
            if (substr_count($serverCode, $statCall) !== 2) {
                throw new \RuntimeException('Cannot apply embedded path checks to the selected PHP CLI Server source');
            }
            $serverCode = str_replace($statCall, 'typephp_cli_server_stat(buf, &sb)', $serverCode);
            $serverCode = $this->insertDeclaration($serverCode, false, true);
            $this->writeIfChanged($serverSource, $serverCode);
            $targets[] = 'typephp-cli-adapter';
            $rules[] = "typephp-cli-adapter: {$object} {$serverObject}\n";
            $rules[] = "{$object}: {$source}\n"
                . "\t\$(LIBTOOL) --tag=CC --mode=compile \$(CC) -Isapi/cli/ -I\$(top_srcdir)/sapi/cli/ "
                . "\$(COMMON_FLAGS) \$(CFLAGS_CLEAN) \$(EXTRA_CFLAGS) -c {$source} -o {$object}\n";
            $rules[] = "{$serverObject}: {$serverSource}\n"
                . "\t\$(LIBTOOL) --tag=CC --mode=compile \$(CC) -Isapi/cli/ -I\$(top_srcdir)/sapi/cli/ "
                . "\$(COMMON_FLAGS) \$(CFLAGS_CLEAN) \$(EXTRA_CFLAGS) -c {$serverSource} -o {$serverObject}\n";
        }
        if (in_array('fpm', $this->targets, true)) {
            if (trim($fpmObjects) === '') {
                throw new \RuntimeException('The private PHP build was not configured with FPM support');
            }
            $source = $directory . '/fpm_main.c';
            $object = $directory . '/fpm_main.lo';
            $code = $this->readSource('/sapi/fpm/fpm/fpm_main.c');
            $needle = '(real_path = tsrm_realpath(script_path_translated, NULL)) == NULL';
            $replacement = '(real_path = typephp_embedded_file_exists(script_path_translated) '
                . '? estrdup(script_path_translated) : tsrm_realpath(script_path_translated, NULL)) == NULL';
            $code = $this->patchOnce($code, $needle, $replacement, 'FPM path check');
            $code = $this->insertDeclaration($code);
            $this->writeIfChanged($source, $code);
            $targets[] = 'typephp-fpm-adapter';
            $rules[] = "typephp-fpm-adapter: {$object}\n";
            $rules[] = "{$object}: {$source}\n"
                . "\t\$(LIBTOOL) --tag=CC --mode=compile \$(CC) -Isapi/fpm/ -I\$(top_srcdir)/sapi/fpm/ "
                . '$(COMMON_FLAGS) $(CFLAGS_CLEAN) $(EXTRA_CFLAGS) -I$(top_srcdir)/sapi/fpm '
                . '-I$(top_srcdir)/sapi/fpm/fpm '
                . "-DZEND_ENABLE_STATIC_TSRMLS_CACHE=1 -c {$source} -o {$object}\n";
        }
        $path = $directory . '/adapters.mk';
        $body = 'include ' . $this->phpBuildDirectory . '/Makefile' . PHP_EOL
            . '.PHONY: ' . implode(' ', $targets) . PHP_EOL
            . implode(PHP_EOL, $rules);
        AtomicFile::write($path, $body);
        return $path;
    }

    private function adapterDirectory(): string
    {
        $runtime = hash('sha256', $this->phpSourceDirectory . "\0" . $this->phpBuildDirectory);
        return $this->buildDirectory . '/cache/sapi/adapters/' . substr($runtime, 0, 16);
    }

    private function insertDeclaration(
        string $code,
        bool $cliArguments = false,
        bool $cliServerStat = false,
    ): string {
        $needle = '#include "php.h"';
        $declarations = 'extern int typephp_embedded_file_exists(const char *path);';
        if ($cliArguments) {
            $declarations .= PHP_EOL
                . 'extern int typephp_cli_prepare_arguments(int *argc, char ***argv, '
                . 'const void *options, char ***allocated_argv);' . PHP_EOL
                . 'extern void typephp_cli_release_arguments(char **allocated_argv);';
        }
        if ($cliServerStat) {
            $declarations .= PHP_EOL . <<<'C'
extern int typephp_embedded_path_kind(const char *path);
static int typephp_cli_server_stat(const char *path, zend_stat_t *statbuf)
{
	int kind = typephp_embedded_path_kind(path);
	if (kind == 0) {
		return php_sys_stat(path, statbuf);
	}
	memset(statbuf, 0, sizeof(*statbuf));
	statbuf->st_mode = kind == 2 ? (S_IFDIR | 0555) : (S_IFREG | 0444);
	statbuf->st_nlink = 1;
	return 0;
}
C;
        }
        $code = preg_replace(
            '/' . preg_quote($needle, '/') . '/',
            $needle . PHP_EOL . $declarations,
            $code,
            1,
            $replacements,
        );
        if (!is_string($code) || $replacements !== 1) {
            throw new \RuntimeException('Cannot apply PHP header include patch to the selected PHP source');
        }
        return $code;
    }

    private function patchCliArguments(string $code): string
    {
        $setup = "\targv = save_ps_args(argc, argv);\n";
        $code = $this->patchOnce(
            $code,
            $setup,
            $setup
                . "\tchar **typephp_saved_argv = argv;\n"
                . "\tchar **typephp_application_argv = NULL;\n"
                . "\tif (typephp_cli_prepare_arguments(&argc, &argv, OPTIONS, &typephp_application_argv) == FAILURE) {\n"
                . "\t\tcleanup_ps_args(typephp_saved_argv);\n"
                . "\t\treturn FAILURE;\n"
                . "\t}\n",
            'CLI argument setup',
        );

        $cleanup = "\tcleanup_ps_args(argv);\n";
        return $this->patchOnce(
            $code,
            $cleanup,
            "\ttypephp_cli_release_arguments(typephp_application_argv);\n"
                . "\tcleanup_ps_args(typephp_saved_argv);\n",
            'CLI argument cleanup',
        );
    }

    private function readSource(string $suffix): string
    {
        $path = $this->phpSourceDirectory . $suffix;
        $code = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($code)) {
            throw new \RuntimeException("Cannot read PHP SAPI source: {$path}");
        }
        return $code;
    }

    private function patchOnce(string $code, string $needle, string $replacement, string $description): string
    {
        if (substr_count($code, $needle) !== 1) {
            throw new \RuntimeException("Cannot apply {$description} patch to the selected PHP source");
        }
        return str_replace($needle, $replacement, $code);
    }

    private function replaceObject(string $objects, string $original, string $replacement): string
    {
        $parts = preg_split('/\s+/', trim($objects)) ?: [];
        $index = array_search($original, $parts, true);
        if ($index === false) {
            throw new \RuntimeException("Cannot locate PHP SAPI object {$original}");
        }
        $parts[$index] = $replacement;
        return implode(' ', $parts);
    }

    /** @param list<string> $basenames */
    private function onlyEntryObjects(string $objects, array $basenames): string
    {
        $matches = [];
        foreach (preg_split('/\s+/', trim($objects)) ?: [] as $object) {
            if (in_array(basename($object), $basenames, true)) {
                $matches[basename($object)] = $object;
            }
        }
        foreach ($basenames as $basename) {
            if (!isset($matches[$basename])) {
                throw new \RuntimeException("Cannot locate patched PHP SAPI entry object {$basename}");
            }
        }
        return implode(' ', array_map(
            static fn (string $basename): string => $matches[$basename],
            $basenames,
        ));
    }

    /** @param list<string> $names @return array<string, string> */
    private function readMakeVariables(string $makefile, array $names): array
    {
        $contents = (string) file_get_contents($makefile);
        $values = [];
        foreach ($names as $name) {
            if (preg_match('/^' . preg_quote($name, '/') . '[ \t]*=[ \t]*(.*)$/m', $contents, $match) === 1) {
                $values[$name] = trim($match[1]);
            }
        }
        return $values;
    }

    /** @param list<string> $command */
    private function run(array $command, string $directory): void
    {
        ($this->output)('$ ' . implode(' ', array_map('escapeshellarg', $command)));
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $directory);
        if (!is_resource($process) || proc_close($process) !== 0) {
            throw new \RuntimeException('Command failed: ' . implode(' ', $command));
        }
    }

    private function mkdir(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create directory: {$directory}");
        }
    }

    private function writeIfChanged(string $path, string $contents): void
    {
        if (is_file($path) && file_get_contents($path) === $contents) {
            return;
        }
        AtomicFile::write($path, $contents);
    }
}
