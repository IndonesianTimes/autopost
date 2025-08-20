<?php
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Templater.php';

for ($i=0; $i<4; $i++) {
  $txt = Templater::render('caption.daily', [
    'title' => 'Sample',
    'rtp'   => 96,
    'cta'   => 'Play now!',
    'platform' => 'telegram'
  ]);
  echo "---- ITER $i ----\n$txt\n\n";
}
