<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

Dockerfile: 8.4 -> Customized image: Added puppeteer to Sail Dockerfile.

# Requerimientos
- Laravel 12
- PHP 8.4
- Spatie/laravel-passkeys
  - Livewire

# LLM reglas:
los comandos se corren usando sail.
siempre usa inglés para el codigo, params, y todo lo que escribas, que no sea texto para el usuario.
clean code:
- siempre que sea posible no anides ifs, ni uses else.
- en las condiciones de los ifs, a menos que sean muy simples, crea una variable que describa el flag y usalo en el if.

