<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 BIOS Programmer Studio</title>
    <script type="module" src="https://unpkg.com/esp-web-tools@10/dist/web/install-button.js?module"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; }
        .card { max-width: 650px; background: #ffffff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin: 10px auto; }
        h2, h3 { margin-top: 0; color: #1a202c; text-align: center; }
        .installer-box { background: #edf2f7; border: 2px dashed #cbd5e0; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .badge { background: #e2e8f0; color: #2d3748; padding: 10px; border-radius: 6px; text-align: center; font-weight: bold; font-size: 14px; margin-bottom: 20px; }
        .group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #4a5568; }
        input[type="file"] { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; background: #f7fafc; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        button { padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; color: #fff; font-size: 14px; transition: opacity 0.2s; }
        .b-check { background: #3182ce; }
        .b-erase { background: #e53e3e; }
        .b-backup { background: #38a169; }
        .b-flash { background: #dd6b20; }
        button:disabled { opacity: 0.4; cursor: not-allowed; }
        
        .progress-container { width: 100%; background-color: #e2e8f0; border-radius: 6px; margin-top: 15px; overflow: hidden; display: none; }
        .progress-bar { width: 0%; height: 22px; background-color: #3182ce; text-align: center; line-height: 22px; color: white; font-weight: bold; font-size: 12px; transition: width 0.1s ease; }

        .chip-info { background: #f7fafc; border: 1px solid #e2e8f0; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; color: #2d3748; display: none; }
        .chip-info p { margin: 4px 0; }
        .chip-info span { font-weight: bold; color: #2b6cb0; }
        
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-ok { background: #c6f6d5; color: #22543d; }
        .status-bad { background: #fed7d7; color: #742a2a; }

        #log { margin-top: 15px; background: #1a202c; color: #48bb78; font-family: monospace; padding: 12px; height: 140px; overflow-y: auto; border-radius: 6px; font-size: 13px; line-height: 1.4; }
    </style>
</head>
<body>

<div class="card">
    <h2>ESP32 BIOS Programmer Studio</h2>

    <div class="installer-box">
        <h3 style="margin-bottom: 6px; font-size: 15px;">1. Flashing Firmware ke ESP32 (Via USB)</h3>
        <p style="font-size: 12px; color: #4a5568; margin-bottom: 10px;">Hubungkan ESP32 ke laptop via kabel USB untuk update program:</p>
        <esp-web-install-button manifest="manifest.json">
            <button slot="activate" style="background: #4c51bf;">Install Program ke ESP32</button>
        </esp-web-install-button>
    </div>

    <div class="badge">2. Mode Kontrol Wi-Fi: 192.168.4.1</div>

    <div id="chipPanel" class="chip-info">
        <p><strong>Manufaktur:</strong> <span id="lblVendor">-</span></p>
        <p><strong>JEDEC ID / Hex:</strong> <span id="lblId">-</span></p>
        <p><strong>Kapasitas Memori:</strong> <span id="lblCapacity">-</span></p>
        <p><strong>Status Kesehatan Chip:</strong> <span id="lblHealth">-</span></p>
    </div>

    <div class="group">
        <label>File BIOS / Firmware (.bin / .rom):</label>
        <input type="file" id="bios_file" accept=".bin,.rom">
    </div>

    <div class="grid">
        <button class="b-check" onclick="checkChip()">1. Cek Detail & Kesehatan Chip</button>
        <button class="b-erase" onclick="eraseChip()">2. Erase & Blank Check</button>
        <button class="b-backup" onclick="backupBIOS()">3. Backup BIOS</button>
        <button class="b-flash" onclick="flashBIOS()">4. Flash & Verifikasi Data</button>
    </div>

    <div id="progContainer" class="progress-container">
        <div id="progressBar" class="progress-bar">0%</div>
    </div>

    <div id="log">> Sistem Siap. Sambungkan Wi-Fi laptop ke "ESP32_BIOS_FLASHER".</div>
</div>

<script>
    const espIP = "192.168.4.1";

    function writeLog(msg) {
        const log = document.getElementById('log');
        log.innerHTML += '<br>> ' + msg;
        log.scrollTop = log.scrollHeight;
    }

    function toggleButtons(disable) {
        const btns = document.querySelectorAll('.grid button');
        btns.forEach(b => b.disabled = disable);
    }

    function showProgress(show) {
        document.getElementById('progContainer').style.display = show ? 'block' : 'none';
        if(!show) updateProgress(0);
    }

    function updateProgress(percent) {
        const bar = document.getElementById('progressBar');
        bar.style.width = percent + '%';
        bar.innerText = percent + '%';
    }

    async function checkChip() {
        writeLog('Membaca informasi detail & status kesehatan IC Flash...');
        toggleButtons(true);
        try {
            const res = await fetch(`http://${espIP}/read_id`);
            const data = await res.json();
            
            if(data.raw_id && data.raw_id !== "0x000000" && data.raw_id !== "0xFFFFFF") {
                document.getElementById('chipPanel').style.display = 'block';
                document.getElementById('lblVendor').innerText = data.manufacturer;
                document.getElementById('lblId').innerText = data.raw_id;
                document.getElementById('lblCapacity').innerText = data.capacity_str;
                
                const healthSpan = document.getElementById('lblHealth');
                if(data.healthy) {
                    healthSpan.innerHTML = '<span class="status-badge status-ok">OK (Normal & Siap Digunakan)</span>';
                } else {
                    healthSpan.innerHTML = '<span class="status-badge status-bad">Busy / Protected</span>';
                }

                writeLog(`Chip Terdeteksi! ${data.manufacturer} (${data.capacity_str}) - Status: Aman`);
            } else {
                document.getElementById('chipPanel').style.display = 'none';
                writeLog('Peringatan: Chip tidak merespons. Periksa perkabelan pin SPI & jepitan SOIC8!');
            }
        } catch(e) {
            writeLog('Error: Gagal terhubung ke ESP32. Pastikan Wi-Fi terhubung ke ESP32_BIOS_FLASHER!');
        }
        toggleButtons(false);
    }

    async function eraseChip() {
        if(!confirm('Hapus seluruh isi cip dan lakukan verifikasi blank check?')) return;
        writeLog('Memulai proses Erase & Blank Check...');
        toggleButtons(true);
        showProgress(true);

        try {
            const response = await fetch(`http://${espIP}/erase`, { method: 'POST' });
            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                
                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');
                for (let line of lines) {
                    if (line.startsWith('data: ')) {
                        const json = JSON.parse(line.substring(6));
                        updateProgress(json.progress);
                        if (json.status === 'erasing') {
                            writeLog(`Penghapusan sektor... (${json.progress}%)`);
                        } else if (json.status === 'verifying') {
                            writeLog(`Verifikasi Blank Check (0xFF)... (${json.progress}%)`);
                        } else if (json.status === 'health_check') {
                            writeLog('Pemeriksaan kesehatan chip akhir...');
                        } else if (json.status === 'done' || json.status === 'error') {
                            writeLog(json.message);
                        }
                    }
                }
            }
        } catch(e) {
            writeLog('Error saat proses Erase.');
        }
        toggleButtons(false);
        setTimeout(() => showProgress(false), 2500);
    }

   async function backupBIOS() {
        writeLog('Memeriksa koneksi cip sebelum backup...');
        
        try {
            // 1. Cek dulu apakah cip terdeteksi normal
            const resId = await fetch(`http://${espIP}/read_id`);
            const dataId = await resId.json();
            
            if(!dataId.raw_id || dataId.raw_id === "0x000000" || dataId.raw_id === "0xFFFFFF") {
                alert('Peringatan: Cip BIOS belum tersambung atau tidak terdeteksi! Periksa kembali jepitan SOIC8.');
                writeLog('Error: Backup dibatalkan karena cip tidak terdeteksi.');
                return;
            }
            
            writeLog(`Cip terdeteksi: ${dataId.manufacturer} (${dataId.capacity_str}). Mengunduh data BIOS...`);
            showProgress(true);
            updateProgress(20);

            // 2. Unduh data biner langsung melalui fetch agar bisa dipaksa berformat .bin
            const response = await fetch(`http://${espIP}/backup`);
            const blob = await response.blob();
            updateProgress(80);

            // 3. Buat nama file otomatis berdasarkan vendor dan kapasitas cip (contoh: Winbond_1MB.bin)
            const cleanVendor = dataId.manufacturer.replace(/[^a-zA-Z0-9]/g, '_');
            const cleanCap = dataId.capacity_str.split(' ')[0] + dataId.capacity_str.split(' ')[1];
            const fileName = `BIOS_${cleanVendor}_${cleanCap}.bin`;

            // 4. Picu unduhan file berformat .bin di browser
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            updateProgress(100);
            writeLog(`Backup Selesai! File tersimpan sebagai: ${fileName}`);
            setTimeout(() => showProgress(false), 2000);

        } catch(e) {
            writeLog('Error: Gagal melakukan proses backup.');
            showProgress(false);
        }
    }

    async function flashBIOS() {
        const input = document.getElementById('bios_file');
        if(!input.files.length) {
            alert('Pilih file .bin firmware terlebih dahulu!');
            return;
        }

        const file = input.files[0];
        const formData = new FormData();
        formData.append('file', file);

        writeLog(`Mengunggah, flashing, & verifikasi readback ${file.name}...`);
        toggleButtons(true);
        showProgress(true);

        let progress = 0;
        let interval = setInterval(() => {
            if (progress < 90) {
                progress += 5;
                updateProgress(progress);
            }
        }, 150);

        try {
            const res = await fetch(`http://${espIP}/flash`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            clearInterval(interval);
            updateProgress(100);
            writeLog(data.message);
            writeLog('Verifikasi Selesai: Data pada cip sudah 100% sama persis dengan file asli. Siap dipasang kembali!');
        } catch(e) {
            clearInterval(interval);
            writeLog('Error saat proses flashing & verifikasi.');
        }
        toggleButtons(false);
        setTimeout(() => showProgress(false), 2500);
    }
</script>

</body>
</html>