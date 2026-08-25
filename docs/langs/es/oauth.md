# OAuth

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../ru/oauth.md) | [English](../ru/oauth.md) | **Español** | [中文](../ru/oauth.md) | [Français](../ru/oauth.md) | [Deutsch](../ru/oauth.md) |


Symfony Shop utiliza OAuth para iniciar sesión y registrarse mediante servicios externos, y también para vincular explícitamente una cuenta externa con un usuario local existente. Estos flujos están separados: que coincida el email no se considera por sí solo una prueba de propiedad de la cuenta local.

En este documento:

- **proveedor** — servicio externo de inicio de sesión, por ejemplo Google o GitHub;
- **ID externo** — identificador de la cuenta del usuario en el proveedor;
- **callback** — regreso del usuario a la aplicación después de autorizarse en el proveedor;
- **state** — token aleatorio que vincula el inicio del flujo OAuth con el callback.

## Proveedores compatibles

| Proveedor | Nombre en la aplicación | Campo `User` |
|---|---|---|
| Google | `google` | `google_id` |
| Yandex | `yandex` | `yandex_id` |
| VKontakte | `vkontakte` | `vkontakte_id` |
| GitHub EN | `github_en` | `github_id` |
| GitHub RU | `github_rus` | `github_id` |
| Facebook | `facebook` | `facebook_id` |
| LinkedIn | `linkedin` | `linkedin_id` |

GitHub EN y GitHub RU usan clientes OAuth distintos, pero comparten el identificador externo `github_id`. Una misma cuenta GitHub no puede vincularse a dos usuarios locales mediante clientes distintos.

Mail.ru no está soportado deliberadamente: no existen cliente OAuth ni rutas para él, y `OAUTH_MAILRU_ENABLED` debe permanecer en `0`.

## Configuración del proveedor

Todos los proveedores implementados están desactivados por defecto.

| Proveedor | Interruptor | Client ID | Client secret |
|---|---|---|---|
| Google | `OAUTH_GOOGLE_ENABLED` | `OAUTH_GOOGLE_ID` | `OAUTH_GOOGLE_SECRET` |
| Yandex | `OAUTH_YANDEX_ENABLED` | `OAUTH_YANDEX_CLIENT_ID` | `OAUTH_YANDEX_CLIENT_SECRET` |
| VKontakte | `OAUTH_VK_ENABLED` | `OAUTH_VK_CLIENT_ID` | `OAUTH_VK_CLIENT_SECRET` |
| GitHub EN | `OAUTH_GITHUB_EN_ENABLED` | `OAUTH_GITHUB_EN_CLIENT_ID` | `OAUTH_GITHUB_EN_CLIENT_SECRET` |
| GitHub RU | `OAUTH_GITHUB_RUS_ENABLED` | `OAUTH_GITHUB_RUS_CLIENT_ID` | `OAUTH_GITHUB_RUS_CLIENT_SECRET` |
| Facebook | `OAUTH_FACEBOOK_ENABLED` | `OAUTH_FACEBOOK_CLIENT_ID` | `OAUTH_FACEBOOK_CLIENT_SECRET` |
| LinkedIn | `OAUTH_LINKEDIN_ENABLED` | `OAUTH_LINKEDIN_CLIENT_ID` | `OAUTH_LINKEDIN_CLIENT_SECRET` |

Ejemplo para `.env.local`:

```dotenv
OAUTH_GOOGLE_ENABLED=1
OAUTH_GOOGLE_ID=YOUR_GOOGLE_CLIENT_ID
OAUTH_GOOGLE_SECRET=YOUR_GOOGLE_CLIENT_SECRET
```

El interruptor se aplica en el servidor, no solo controla la visibilidad del botón. Con `*_ENABLED=0`, los nuevos flujos de inicio de sesión, registro y vinculación se bloquean antes de contactar con el proveedor.

Las credenciales reales no se añaden a Git. La prioridad entre `.env.local` y variables Docker se explica en la [guía de configuración](configuration.md).

## Inicio de sesión y registro normales

Los casos principales se comparan mejor en conjunto:

| Situación | Resultado | Qué no ocurre |
|---|---|---|
| El ID externo ya está vinculado | Se inicia sesión en la misma cuenta local | el email local no se sustituye por datos del proveedor y la vinculación no se reescribe |
| El ID externo es nuevo, pero ya existe localmente ese email | El acceso se rechaza con un error neutro | no hay vinculación automática, acceso a la cuenta encontrada, creación de usuario ni email de registro |
| Tanto ID externo como email son nuevos | Se crea un usuario local no verificado y se inicia sesión mediante OAuth | el proveedor no verifica automáticamente el email local y no se envía al usuario una contraseña aleatoria |
| El proveedor no devuelve email | El acceso se rechaza con un error neutro | no se crea usuario ni se modifican datos |

Si el ID externo ya está vinculado a un usuario local eliminado, el acceso también se rechaza.

Para un usuario nuevo, la aplicación guarda email e ID externo, mantiene `isVerified=false`, genera una contraseña interna aleatoria y almacena únicamente su hash. Después de guardar al usuario se inicia el flujo normal de verificación de email. El usuario puede definir una contraseña local conocida mediante recuperación de contraseña.

El email de registro se procesa mediante Messenger `async`. Docker Compose no tiene actualmente un worker permanente, por lo que para comprobar este escenario localmente hay que ejecutar aparte `make console CMD='messenger:consume async -vv'`. Consulta la [sección de correo y Messenger](configuration.md).

Los errores de intercambio del token OAuth o de obtención del perfil se convierten en un error seguro de la aplicación sin mostrar al usuario la respuesta del proveedor.

## Vinculación explícita a una cuenta existente

La vinculación la inicia un usuario local ya autenticado.

| Paso | Qué ocurre |
|---|---|
| `GET` de la página de vinculación | Se muestra un formulario de confirmación; no cambia ningún dato |
| `POST` del formulario | Se verifican la contraseña actual y el token CSRF |
| Redirección al proveedor | Se crea una intención de vinculación de un solo uso en la sesión actual |
| Callback del proveedor | Se verifican usuario, proveedor, OAuth `state` y vida útil de la intención |
| Éxito | Solo se escribe el ID externo del proveedor elegido |

La intención se conserva en la sesión como máximo 600 segundos y queda vinculada al usuario y proveedor concretos. No se guarda el `state` OAuth original, sino su hash SHA-256. La intención es de un solo uso, por lo que repetir el callback se rechaza.

La vinculación no busca usuarios por email ni cambia la sesión de acceso actual. Si el ID externo ya pertenece a otro usuario, no se crea la vinculación. La última barrera contra escrituras concurrentes es la restricción única de la base de datos.

## Desvinculación

La desvinculación también se realiza desde una cuenta autenticada.

| Paso | Qué ocurre |
|---|---|
| `GET` de la página de desvinculación | Se muestra un formulario; el ID externo no cambia |
| `POST` del formulario | Se verifican la contraseña actual y el token CSRF |
| Éxito | Solo se borra el campo OAuth seleccionado |

El campo `User` se elige en el servidor a partir de un nombre de proveedor permitido. El cliente no envía un nombre de método setter ni un nombre de campo arbitrario.

Si el proveedor se desactiva después de haberse vinculado, el usuario todavía puede eliminar esa vinculación. El interruptor bloquea nuevos flujos OAuth, pero no impide una desvinculación segura.

## Rutas

Las rutas OAuth normales están bajo `/{_locale}`, con soporte para `ru` y `en`.

| Proveedor | Inicio del flujo OAuth | Callback |
|---|---|---|
| Google | `/{_locale}/connect/google` | `/{_locale}/connect/google/check` |
| Yandex | `/{_locale}/connect/yandex` | `/{_locale}/connect/yandex/check` |
| VKontakte | `/{_locale}/connect/vkontakte` | `/{_locale}/connect/vkontakte/check` |
| GitHub EN | `/{_locale}/connect/github-en` | `/{_locale}/connect/github-en/check` |
| GitHub RU | `/{_locale}/connect/github-ru` | `/{_locale}/connect/github-ru/check` |
| Facebook | `/{_locale}/connect/facebook` | `/{_locale}/connect/facebook/check` |
| LinkedIn | `/{_locale}/connect/linkedin` | `/{_locale}/connect/linkedin/check` |

Estas rutas se usan en el flujo GET del navegador, pero la configuración YAML actual no establece restricciones de métodos HTTP separadas para ellas a nivel de Symfony Router.

Las operaciones del área personal tienen métodos explícitos:

| Operación | Ruta | Métodos |
|---|---|---|
| Vincular | `/{_locale}/profile/oauth/{provider}/link` | `GET`, `POST` |
| Desvincular | `/{_locale}/profile/oauth/{provider}/unlink` | `GET`, `POST` |

Para `{provider}` se admiten `google`, `yandex`, `vkontakte`, `github_en`, `github_rus`, `facebook` y `linkedin`.

## Unicidad del ID externo

Los campos `google_id`, `yandex_id`, `vkontakte_id`, `github_id`, `facebook_id` y `linkedin_id` están protegidos mediante restricciones únicas en Doctrine y en la base de datos. Una cuenta externa no puede pertenecer simultáneamente a dos usuarios locales.
