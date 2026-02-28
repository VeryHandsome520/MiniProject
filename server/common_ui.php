<?php
// ฟังก์ชันแสดง CSS และฟอนต์ของธีม
function renderThemeHead()
{
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f0f2f5;
            --text-color: #212529;
            --card-bg: #ffffff;
            --primary-color: #0d6efd;
            --font-size-base: 16px;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Outfit', sans-serif;
            font-size: var(--font-size-base);
            transition: background-color 0.3s, color 0.3s;
        }

        /* สไตล์การ์ดที่ใช้ร่วมกัน */
        .card {
            background-color: var(--card-bg);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s, background-color 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 700;
        }

        .btn-custom {
            border-radius: 10px;
            font-weight: 500;
        }

        /* ปุ่มตั้งค่าลอยตัว */
        .settings-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: none;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .settings-btn:hover {
            transform: scale(1.1);
        }

        /* ปรับแต่งหน้าต่าง Modal */
        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-radius: 20px;
        }

        .online {
            color: #198754;
            font-weight: bold;
        }

        .offline {
            color: #6c757d;
        }
    </style>
    <?php
}

// ฟังก์ชันแสดงปุ่มตั้งค่าธีมและ Modal
function renderThemeBody()
{
    ?>
    <!-- ปุ่มตั้งค่า -->
    <button class="settings-btn" data-bs-toggle="modal" data-bs-target="#settingsModal">⚙️</button>

    <!-- หน้าต่างตั้งค่าธีม -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Theme Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Background Color</label>
                        <input type="color" id="bgColorPicker" class="form-control form-control-color w-100"
                            value="#f0f2f5">
                    </div>
                    <div class="mb-3">
                        <label>Card Color</label>
                        <input type="color" id="cardColorPicker" class="form-control form-control-color w-100"
                            value="#ffffff">
                    </div>
                    <div class="mb-3">
                        <label>Text Color</label>
                        <input type="color" id="textColorPicker" class="form-control form-control-color w-100"
                            value="#212529">
                    </div>
                    <div class="mb-3">
                        <label>Font Size: <span id="fontSizeVal">16</span>px</label>
                        <input type="range" id="fontSizeSlider" class="form-range" min="12" max="24" value="16">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="resetTheme()">Reset Default</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- ลอจิกจัดการธีม ---
        const root = document.documentElement;

        // ใช้ธีมที่บันทึกไว้
        function applyTheme() {
            const savedTheme = JSON.parse(localStorage.getItem('themeSettings'));
            if (savedTheme) {
                root.style.setProperty('--bg-color', savedTheme.bg);
                root.style.setProperty('--card-bg', savedTheme.card);
                root.style.setProperty('--text-color', savedTheme.text);
                root.style.setProperty('--font-size-base', savedTheme.font + 'px');

                // อัปเดต input ถ้ามีในหน้า
                if (document.getElementById('bgColorPicker')) {
                    document.getElementById('bgColorPicker').value = savedTheme.bg;
                    document.getElementById('cardColorPicker').value = savedTheme.card;
                    document.getElementById('textColorPicker').value = savedTheme.text;
                    document.getElementById('fontSizeSlider').value = savedTheme.font;
                    document.getElementById('fontSizeVal').textContent = savedTheme.font;
                }
            }
        }

        // บันทึกธีมลง localStorage
        function saveTheme() {
            const theme = {
                bg: document.getElementById('bgColorPicker').value,
                card: document.getElementById('cardColorPicker').value,
                text: document.getElementById('textColorPicker').value,
                font: document.getElementById('fontSizeSlider').value
            };
            localStorage.setItem('themeSettings', JSON.stringify(theme));
            applyTheme();
        }

        // รีเซ็ตธีมเป็นค่าเริ่มต้น
        function resetTheme() {
            localStorage.removeItem('themeSettings');
            location.reload();
        }

        // ผูก Event Listener ถ้ามี input ในหน้า
        document.addEventListener('DOMContentLoaded', function () {
            const ids = ['bgColorPicker', 'cardColorPicker', 'textColorPicker'];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', saveTheme);
            });

            const slider = document.getElementById('fontSizeSlider');
            if (slider) {
                slider.addEventListener('input', function () {
                    document.getElementById('fontSizeVal').textContent = this.value;
                    saveTheme();
                });
            }

            applyTheme();
        });
    </script>
    <?php
}
?>