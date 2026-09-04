@php
  // Explicit presentation opt-in: public pages can continue sharing the Vuexy shell.
  $backendWorkspace = true;
  $pageConfigs = array_merge($pageConfigs ?? [], [
    'myLayout' => 'horizontal',
    'myStyle' => 'light',
    'hasCustomizer' => false,
  ]);
  $container = 'container-xxl ct-backend-container';
  $containerNav = 'container-xxl ct-backend-nav-container';
@endphp
@extends('layouts.horizontalLayout')
