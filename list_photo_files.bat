@echo off
echo Saving file list with folder structure to logs.txt...
echo ------------------------------------------
dir photos /b /s > logs.txt
echo Done. Check logs.txt for results.
pause
