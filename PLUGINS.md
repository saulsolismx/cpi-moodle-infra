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

---

| Campo | Valor |
|---|---|
| **Nombre** | Level Up XP - Gamification |
| **Componente** | `block_xp` |
| **Repo** | https://github.com/FMCorz/moodle-block_xp |
| **Tag fijado** | `v20.0` |
| **Commit fijado** | `65541fdc9c77511a906353f6660e195eeaa51893` |
| **Versión** | 20.0 (2026042001) |
| **Ubicación** | `moodle/public/blocks/xp/` |
| **Compatibilidad** | Moodle 4.1 – 5.2 |

> **Nota:** usar siempre el tag estable (`v20.0`), NO la rama `master` (es de desarrollo, según docs oficiales).

### Comando de instalación

```bash
git clone --branch v20.0 https://github.com/FMCorz/moodle-block_xp.git moodle/public/blocks/xp
rm -rf moodle/public/blocks/xp/.git
sudo chown -R 33:33 moodle/public/blocks/xp
docker compose exec -u www-data php php /var/www/html/admin/cli/upgrade.php --non-interactive
```
