- linux x64
  https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-linux-amd64-pgo
- linux arm64
  https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-linux-arm64
- macos x64
  https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-macos-amd64
- macos arm64
  https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-macos-arm64
- windows x64
  https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-windows-amd64-pgo.exe

~~~ php
// Resume + streaming download
Http::to('https://cdn.example.com/big.iso')
->resumeFromBytes(filesize('/tmp/big.iso') ?: 0)
->saveTo('/tmp/big.iso')
->get();
~~~