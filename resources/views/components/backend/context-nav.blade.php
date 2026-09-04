@props(['label' => 'Workspace navigation'])
<nav {{ $attributes->class(['ct-context-nav']) }} aria-label="{{ $label }}">{{ $slot }}</nav>
