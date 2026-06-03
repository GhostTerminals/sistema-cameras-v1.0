# Public (Document Root)

Aponte seu servidor web (Apache/Nginx) para este diretório.

## Cache estático

Para habilitar cache de assets estáticos (JS/CSS/imagens), configure no seu servidor:

**Apache (`.htaccess`):**
```
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
</IfModule>
```

**IIS (`Web.config`):**
```xml
<staticContent>
    <clientCache cacheControlMode="UseMaxAge" cacheControlMaxAge="365.00:00:00" />
</staticContent>
```
