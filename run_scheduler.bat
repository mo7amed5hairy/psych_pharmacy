@echo off
setlocal
cd /d "c:\Apache24\htdocs\psych_pharmacy"

:loop
echo [%date% %time%] Running Laravel Scheduler...
php artisan schedule:run >> "c:\Apache24\htdocs\psych_pharmacy\storage\logs\scheduler.log" 2>&1

:: If you are using Windows Task Scheduler to repeat every minute, 
:: you don't need this loop. But if you want to run it manually 
:: and keep it alive, uncomment the following two lines:
timeout /t 60 /nobreak
goto loop

