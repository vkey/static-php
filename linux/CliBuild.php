<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

class CliBuild
{
    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $allStatic = false): void
    {
        Log::i("building cli");
        $ret = 0;

        $extra_libs = implode(' ', $this->config->getAllStaticLibFiles());
        $envs = $this->config->configureEnv;
        $seds = '';

        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
                $envs .= ' CFLAGS="-static-libgcc -I' . realpath('include') . '" ' .
                    $this->config->libc->getCCEnv(true);
                $seds =
                ' sed -i "s|#define HAVE_STRLCPY 1||g" main/php_config.h && ' .
                ' sed -i "s|#define HAVE_STRLCAT 1||g" main/php_config.h && ';
                /*
                $info = `echo | musl-gcc -v -x c - 2>&1`;
                $r = preg_match("/'-specs=([^']+)\/musl-gcc.specs'/", $info, $m);
                if (!$r) {
                    loge("failed to find musl libc");
                    exit(1);
                }
                $spec_path = $m[1];
                // remove stubs for dl things
                copy("$spec_path/libc.a", 'lib/dl_libc.a');
                passthru('ar d lib/dl_libc.a dlopen.lo dlclose.lo dlsym.lo dlinfo.lo dlerror.lo dladdr.lo');
                $extra_libs  .= ' ' . realpath('lib/dl_libc.a');
                */
                break;
            case CLib::GLIBC:
                $envs = ' CFLAGS="-static-libgcc -I' . realpath('include') . '" ';
                $extra_libs  .= ' -lrt -lm -lpthread -lresolv';
                break;
            default:
                throw new Exception('not implemented');
        }
        $curl = $this->config->getExt('curl');
        if ($curl) {
            Log::i('patching configure for curl checks');
            $configure = file_get_contents('src/php-src/configure');
            $configure = preg_replace('/-lcurl/', $curl->getStaticLibFiles(), $configure);
            file_put_contents('src/php-src/configure', $configure);
        }
        file_put_contents('/tmp/comment', "... If we meet some day, and you think this stuff is worth it, you can buy me a beer in return.\0");
        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no '.
                '--enable-shared=no '.
                '--enable-static=yes '.
                '--disable-all '.
                '--disable-cgi '.
                '--disable-phpdbg '.
                '--enable-cli '.
                ($this->config->zts ? '--enable-zts': '').' '.
                Extension::makeExtensionArgs($this->config) . ' ' .
                $envs . ' ' .
                ' && ' .
                $seds . ' ' .
                "make -j{$this->config->concurrency} "  .
                'EXTRA_CFLAGS="-g -Os -fno-ident -Xcompiler -march=nehalem -Xcompiler -mtune=haswell" ' .
                "EXTRA_LIBS=\"$extra_libs\" " .
                ($allStatic ? 'EXTRA_LDFLAGS_PROGRAM=-all-static ': '') .
                'cli ',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build micro");
        }
    }
}