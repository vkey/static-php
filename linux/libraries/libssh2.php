<?php

class Liblibssh2 implements ILibrary
{
    private string $name = 'libssh2';
    private array $staticLibs = [
        'libssh2.a',
    ];
    private array $headers = [
        'libssh2.h',
        'libssh2_publickey.h',
        'libssh2_sftp.h',
    ];
    private array $pkgconfs = [
        'libssh2.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: libssh2
URL: https://www.libssh2.org/
Description: Library for SSH-based communication
Version: 1.10.0
Requires.private: libssl libcrypto zlib
Libs: -L${libdir} -lssh2
Libs.private:
Cflags: -I${includedir}
EOF
    ];

    use Library;

    private function build()
    {
        Log::i("building {$this->name}");
        $libopenssl = $this->config->getLib('openssl');
        if (!$libopenssl) {
            throw new Exception('libssh2 requires openssl');
        }
        $ret = 0;

        $libs = 'LIBS="' . $libopenssl->getStaticLibFiles() . ' ';

        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        if ($libzlib) {
            $zlib = '--with-libz --with-libz-prefix=' . realpath('.');
            $libs .= $libzlib->getStaticLibFiles() . ' ';
        }

        $libs = rtrim($libs) . '"';

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . $this->config->libc->getCCEnv() . ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-rpath ' .
                '--with-crypto=openssl ' .
                '--with-libssl-prefix=' . realpath('.') . ' ' .
                '--with-libcrypto-prefix=' . realpath('.') . ' ' .
                $zlib . ' ' .
                $libs . ' ' .
                '--prefix= && ' . //use prefix=/
                "make -j {$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}