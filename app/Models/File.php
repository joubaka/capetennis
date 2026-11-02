<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    /** Resolve the same legacy/local paths for document links and downloads. */
    public function resolvedLocalPath(bool $requireReadable = true): ?string
    {
        $path = (string) $this->path;
        if ($path === '' || str_contains($path, "\0") || preg_match('~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~', $path)) return null;
        $absolute = str_starts_with($path, '/') || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
        if (!$absolute) $path = storage_path('app/'.ltrim($path, '/'));
        // Preserve existing absolute/local records before trying the narrowly
        // recognized migrated attachment layouts below.
        if (is_file($path) && (!$requireReadable || is_readable($path))) return $path;
        $legacy = $this->legacyAttachmentFilename();
        $candidates = $legacy === null ? [] : [[storage_path($legacy), storage_path()]];
        if ($portable = $this->portableAttachmentPath()) $candidates[] = [$portable, storage_path('app')];
        foreach ($candidates as [$candidate, $root]) {
            if ($this->containedFile($candidate, $root) && (!$requireReadable || is_readable($candidate))) return $candidate;
        }
        return null;
    }

    public function isAvailable(): bool
    {
        return $this->resolvedLocalPath() !== null;
    }

    /** Known attachment layouts only; never guess from an unrelated absolute basename. */
    public function portableAttachmentPath(): ?string
    {
        $path = (string) $this->path;
        if (preg_match('~\Apublic/files/([^/\\\\\x00-\x1f]+)\z~u', $path, $match)) {
            return $this->safeAttachmentFilename($match[1]) ? storage_path('app/public/files/'.$match[1]) : null;
        }
        $filename = $this->legacyAttachmentFilename();
        return $filename === null ? null : storage_path('app/public/files/legacy/'.$filename);
    }

    public function legacyAttachmentFilename(): ?string
    {
        // Earlier uploads persisted the hosting account's physical document
        // root. The same attachment remains in storage/ after a host migration.
        if (!preg_match('~\A/home/batawrry/domains/capetennis\.co\.za/public_html/storage/([^/\\\\\x00-\x1f]+)\z~u', (string) $this->path, $match)) return null;
        return $this->safeAttachmentFilename($match[1]) && in_array($match[1], [$this->name, $this->name.'.pdf'], true) ? $match[1] : null;
    }

    public function matchesSourceFilename(string $filename): bool
    {
        $destination = $this->portableAttachmentPath();
        if ($destination === null || $filename !== basename($destination)) return false;
        return $filename === $this->name
            || ($this->legacyAttachmentFilename() !== null && $filename === $this->name.'.pdf');
    }

    private function safeAttachmentFilename(string $filename): bool
    {
        return !in_array($filename, ['.', '..'], true) && !str_contains($filename, ':');
    }

    private function containedFile(string $candidate, string $root): bool
    {
        $resolved = realpath($candidate);
        $directory = realpath($root);
        if ($resolved === false || $directory === false || is_link($candidate) || !is_file($resolved)) return false;
        $resolved = str_replace('\\', '/', $resolved);
        $logicalRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
        $logicalCandidate = str_replace('\\', '/', $candidate);
        if (!str_starts_with($logicalCandidate, $logicalRoot)) return false;
        $expected = rtrim(str_replace('\\', '/', $directory), '/').'/'.substr($logicalCandidate, strlen($logicalRoot));
        if (PHP_OS_FAMILY === 'Windows') { $resolved = strtolower($resolved); $expected = strtolower($expected); }
        // An exact canonical path also rejects redirected intermediate cache
        // directories, including junctions pointing elsewhere within storage.
        return $resolved === $expected;
    }
}
