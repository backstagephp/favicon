<?php

namespace Backstage\Favicon\View\Components;

use Backstage\Favicon\FaviconManager;
use Illuminate\View\Component;
use Illuminate\View\View;

class FaviconComponent extends Component
{
    public string $src;

    public function __construct(
        FaviconManager $manager,
        public string $url,
        public ?string $type = null,
        public ?int $size = null,
    ) {
        $config = $manager->config();
        $this->type = $type ?? $config['default_type'];
        $this->size = $this->type === 'svg' ? null : ($size ?? $config['default_size']);
        $this->src = $manager->for($url)->url($this->type, $this->size);
    }

    public function render(): View
    {
        return view('favicon::components.favicon');
    }
}
