@echo off
cd /d "%~dp0"
echo Ihira Live sur http://localhost:8000  (fermer cette fenetre pour arreter)
start "" /b cmd /c "timeout /t 2 >nul & start http://localhost:8000/login.php"
php -d upload_max_filesize=32M -d post_max_size=64M -d memory_limit=256M -S localhost:8000 -t public
pause
