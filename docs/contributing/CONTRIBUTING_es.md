# Contribuir a Symfony Shop

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../.github/CONTRIBUTING.md) | [English](CONTRIBUTING_en.md) | **Español** | [中文](CONTRIBUTING_zh.md) | [Français](CONTRIBUTING_fr.md) | [Deutsch](CONTRIBUTING_de.md) |

Gracias por tu interés en Symfony Shop. Es un proyecto educativo de comercio electrónico con Symfony, entorno Docker, PostgreSQL, API Platform, OAuth y algunos componentes interactivos en Vue.

## Antes de empezar

Revisa las Discussions, Issues y Pull Requests existentes y procura que cada cambio resuelva una tarea clara. Las preguntas e ideas se comentan primero en [GitHub Discussions](https://github.com/yaleksandr89/symfony-shop/discussions); los errores reproducibles y mejoras concretas se registran como Issues; los problemas de seguridad deben comunicarse según la [política de seguridad](../security/SECURITY_es.md) sin publicar detalles de explotación.

## Límites del proyecto

- El entorno local compatible utiliza Docker Compose y Makefile.
- PHP, Composer, PostgreSQL, Node.js y el entorno de navegador no se ejecutan directamente en el host como parte del flujo habitual.
- Los cambios no deben debilitar de forma silenciosa las reglas de acceso, los flujos OAuth, la integridad del carrito/pedidos ni otros contratos existentes.
- No incluyas refactorizaciones amplias ni actualizaciones de dependencias que no estén relacionadas con la tarea.
- La arquitectura frontend con Vue 2 se mantiene hasta la migración separada a Inertia.js y Vue 3.

La arquitectura está descrita en [`docs/architecture.md`](../architecture.md) y los comandos de desarrollo en [`docs/development.md`](../development.md).

## Ramas

Crea una rama temática desde el `master` actual. El nombre debe describir brevemente el cambio, por ejemplo:

```text
fix/cart-quantity
docs/oauth
refactor/catalog-query
```

Los cambios llegan a `master` mediante Pull Request.

## Commits

El proyecto utiliza Conventional Commits con la descripción escrita en ruso:

```text
fix: исправить проверку количества товара
docs: уточнить настройку OAuth
refactor: упростить выборку каталога
```

Cada commit debe contener un grupo de cambios lógicamente coherente.

## Comprobaciones locales

Lee el Makefile actual antes de ejecutar comandos. Comprobaciones principales:

| Comando | Propósito |
|---|---|
| `make check` | ESLint + comprobación PHP-CS-Fixer + PHPStan |
| `make test-unit` | pruebas unitarias |
| `make test-integration` | pruebas de integración |
| `make test-functional` | pruebas funcionales |
| `make test-functional-panther` | pruebas de navegador Panther |
| `make test-all CONFIRM=testdb` | conjunto completo, incluido Panther |

Ejecuta las comprobaciones relacionadas con el cambio. Usa el conjunto completo cuando se modifiquen límites compartidos de la aplicación o antes de la verificación final de un cambio grande.

## Pull Request

En la descripción del Pull Request indica:

- qué ha cambiado y por qué;
- cómo se ha verificado;
- si se requieren pasos manuales;
- si afecta a configuración, datos, OAuth, permisos u otro contrato importante;
- si se actualizó la documentación cuando cambió el uso público del proyecto.

## Lista de comprobación

- No hay secretos, credenciales OAuth reales, tokens de acceso, cookies, identificadores de sesión ni contenido de `.env*` local.
- No hay cambios ajenos a la tarea en el diff.
- `git diff --check` pasa.
- Se han ejecutado las comprobaciones relevantes.
- Las pruebas nuevas protegen un comportamiento concreto, no se añaden por cantidad.
- La documentación se actualiza cuando cambia un contrato público, la configuración o el modo de arranque.
