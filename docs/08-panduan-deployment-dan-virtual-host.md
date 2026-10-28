# 🌐 08. Panduan Deployment & Virtual Host

Panduan konfigurasi domain lokal Laragon (`simod-enterprise.test`) dan prosedur deployment ke server produksi (VPS / Hostinger).

---

## 1. Setup Domain Lokal Laragon (`simod-enterprise.test`)

Virtual host Apache untuk SIMOD Enterprise telah dibuat pada:
`D:\Foto\laragon\etc\apache2\sites-enabled\auto.simod-enterprise.test.conf`

```apache
define ROOT "D:/Foto/laragon/www/simod-enterprise/public"
define SITE "simod-enterprise.test"

<VirtualHost *:80> 
    DocumentRoot "${ROOT}"
    ServerName ${SITE}
    ServerAlias *.${SITE}
    <Directory "${ROOT}">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Langkah Menghubungkan ke Windows:
1. Buka aplikasi **Laragon** di Windows.
2. Klik tombol **"Stop All"** lalu klik **"Start All"** (atau klik kanan tray Laragon &rarr; **Apache** &rarr; **Reload**).
3. Laragon akan otomatis menambahkan baris berikut ke `C:\Windows\System32\drivers\etc\hosts`:
   ```text
   127.0.0.1      simod-enterprise.test     #laragon magic!
   ```
4. Buka peramban di URL `http://simod-enterprise.test`.

---

## 2. Prosedur Deployment ke Remote Server / VPS Hostinger

### Langkah 1: Backup Basis Data Produksi
Sebelum melakukan pembaruan di server remote, buat cadangan basis data:
```bash
mysqldump -u <USER> -p <DATABASE_PRODUKSI> > ~/backups/backup_$(date +%s).sql
```

### Langkah 2: Sinkronisasi Kode Git
```bash
cd /var/www/simod-enterprise
git pull origin main
```

### Langkah 3: Kompilasi & Optimasi Laravel
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Langkah 4: Bangun Frontend Vite
```bash
npm install
npm run build
```

### Langkah 5: Restart Service Worker & Web Server
```bash
sudo systemctl reload nginx
# Atau restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

---

## 3. Konfigurasi Nginx untuk Server Remote

```nginx
server {
    listen 80;
    server_name simod-enterprise.polri.go.id;
    root /var/www/simod-enterprise/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

