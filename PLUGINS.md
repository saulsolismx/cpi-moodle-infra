# Plugins de terceros — CPI Virtual

Los plugins listados aquí **NO están en git** (el directorio `moodle/` está en `.gitignore`).
Al desplegar en un servidor nuevo, deben reinstalarse manualmente usando las referencias exactas de esta tabla.

Tras instalar cada plugin: `sudo chown -R 33:33 <ubicación>` y luego:
```bash
docker compose exec -u www-data php php /var/www/html/admin/cli/upgrade.php --non-interactive
```

---

## Plugins instalados

| Campo | Valor |
|---|---|
| **Nombre** | My Progress |
| **Componente** | `block_myprogress` |
| **Repo** | https://github.com/E-learningTouch/moodle-block_myprogress |
| **Commit fijado** | `49d6871e074b1112d6e3efa953a11fcde49d3121` |
| **Versión** | 1.1.0 (2026041600) |
| **Ubicación** | `moodle/public/blocks/myprogress/` |
| **Compatibilidad** | Moodle 4.5 – 5.2 |

### Comando de instalación

```bash
git clone https://github.com/E-learningTouch/moodle-block_myprogress.git moodle/public/blocks/myprogress
cd moodle/public/blocks/myprogress
git checkout 49d6871e074b1112d6e3efa953a11fcde49d3121
rm -rf .git
cd ../../..
sudo chown -R 33:33 moodle/public/blocks/myprogress
docker compose exec -u www-data php php /var/www/html/admin/cli/upgrade.php --non-interactive
```
