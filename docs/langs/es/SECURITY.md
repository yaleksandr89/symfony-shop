# Seguridad

## Elige idioma

| Русский | English | Español | 中文 | Français | Deutsch |
|---|---|---|---|---|---|
| [Русский](../../../.github/SECURITY.md) | [English](../en/SECURITY.md) | **Español** | [中文](../zh/SECURITY.md) | [Français](../fr/SECURITY.md) | [Deutsch](../de/SECURITY.md) |

Por favor, informa de posibles vulnerabilidades de forma responsable. Symfony Shop es un proyecto educativo público, pero los problemas de autenticación, OAuth, carrito, checkout, API, tratamiento de datos de usuario y configuración se consideran problemas normales de seguridad de una aplicación.

## Qué conviene comunicar en privado

- bypass de autenticación o autorización;
- posibilidad de entrar en la cuenta de otro usuario o vincular una identidad OAuth externa al usuario local equivocado;
- bypass de la protección CSRF en operaciones que modifican estado;
- inyección SQL/DQL;
- XSS almacenado o reflejado;
- lectura o modificación del carrito, pedido o datos administrativos de otro usuario;
- bypass de restricciones de la API o exposición de datos a través de la API;
- exposición de `.env`, credenciales OAuth, tokens de acceso, cookies, identificadores de sesión, excepciones internas u otra información sensible;
- posibilidad de saltarse el interruptor del servidor que deshabilita un proveedor OAuth;
- una vulnerabilidad explotable de una dependencia que afecte de forma importante al proyecto;
- compromiso de CI, código fuente o cadena de suministro de dependencias.

## Qué puede publicarse en Issues

- un error reproducible de interfaz sin impacto de seguridad;
- un error de catálogo, carrito o administración sin acceso a datos de otros usuarios;
- un problema de Docker/bootstrap o compatibilidad;
- un error de documentación;
- una propuesta de mejora.

Si no estás seguro de si el problema afecta a la seguridad, utiliza primero un canal privado.

## Cómo informar

- Si la sección Security del repositorio ofrece un formulario privado para vulnerabilidades, úsalo en primer lugar.
- No publiques código de explotación, secretos reales, credenciales OAuth, tokens de acceso, cookies, identificadores de sesión ni contenido de `.env*` local en Issues, Pull Requests o logs.
- Si no existe un formulario privado, crea un Issue público mínimo sin detalles de explotación y solicita un canal privado.
- No publiques detalles técnicos de explotación antes de que exista una corrección.

## Qué incluir

Siempre que sea posible, indica:

- SHA del commit o rama;
- área afectada de la aplicación;
- impacto;
- pasos mínimos para reproducir;
- fragmento saneado de petición, respuesta o log si es relevante;
- versiones de PHP/Symfony/PostgreSQL si son importantes para reproducir.

Utiliza solo datos sintéticos. No adjuntes contraseñas reales, tokens, ID externos, cookies, identificadores de sesión ni contenido de `.env*` local.

## Qué ocurrirá después

- El proyecto está mantenido por un único autor; no existe un SLA garantizado.
- El informe se revisará y, cuando sea necesario, se preparará una corrección y una comprobación de regresión.
- No se promete un programa de recompensas por vulnerabilidades.
- Es preferible retrasar la divulgación pública hasta que exista una corrección.
