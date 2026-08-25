# Portable media binaries

Place the standalone Windows binaries in `tools/bin`:

- `yt-dlp.exe`
- `ffmpeg.exe`
- `ffprobe.exe`

The paths in `.env` are relative to the project root, so the project folder can
be moved to another Windows machine without installing Python, yt-dlp, ffmpeg,
or Scoop. Keep all three executables together in the same directory.

After replacing a binary or changing `.env`, run:

```powershell
php artisan config:clear
php artisan queue:restart
```
