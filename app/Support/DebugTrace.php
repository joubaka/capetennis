<?php
// app/Support/DebugTrace.php
namespace App\Support;

class DebugTrace
{
  protected array $entries = [];
  protected bool $enabled;

  public function __construct(bool $enabled = false)
  {
    $this->enabled = $enabled;
  }

  public function enabled(): bool
  {
    return $this->enabled;
  }

  public function step(string $label, array $ctx = []): void
  {
    if (!$this->enabled)
      return;
    $this->entries[] = ['type' => 'step', 'label' => $label, 'ctx' => $ctx, 'ts' => now()->toISOString()];
  }

  public function info(string $msg, array $ctx = []): void
  {
    if (!$this->enabled)
      return;
    $this->entries[] = ['type' => 'info', 'msg' => $msg, 'ctx' => $ctx, 'ts' => now()->toISOString()];
  }

  public function warn(string $msg, array $ctx = []): void
  {
    if (!$this->enabled)
      return;
    $this->entries[] = ['type' => 'warn', 'msg' => $msg, 'ctx' => $ctx, 'ts' => now()->toISOString()];
  }

  public function dump(): array
  {
    return $this->entries;
  }
}
