<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>Sync TTNplus API</title>
    <style>
        body {
            font-family: Arial;
            background: #f7f7f7;
        }

        .box {
            position: relative;
            /* สำคัญ */
            width: 700px;
            margin: 40px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);
        }

        .bar {
            position: relative;
            width: 100%;
            background: #eee;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 10px;
            height: 35px;
        }

        /* แถบสีเขียว */
        .bar::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: var(--percent, 0%);
            background: #4caf50;
            transition: width .3s;
        }

        /* ตัวเลข % */
        .bar span {
            position: absolute;
            /* สำคัญ */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-weight: bold;
            color: #fff;
            pointer-events: none;

            font-variant-numeric: tabular-nums;
            /* กันตัวเลขกระโดด */
        }

        .log {
            margin-top: 15px;
            font-size: 14px;
            max-height: 300px;
            overflow: auto;
            background: #fafafa;
            padding: 10px;
            border-radius: 6px;
        }

        .ok {
            color: green;
        }

        .err {
            color: red;
        }

        .action {
            position: absolute;
            right: 20px;
            bottom: 20px;
        }

        button {
            padding: 10px 20px;
            font-size: 15px;
            cursor: pointer;
            border-radius: 999px;
            border: none;
            background: #2196f3;
            color: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .2);
        }

        button:disabled {
            background: #999;
            box-shadow: none;
        }

        button.ready {
            background: #4caf50;
        }

        button.running {
            background: #999;
        }
    </style>
</head>

<body>

    <div class="box">
        <h2>🔄 Sync TTNplus API</h2>

        <div class="bar">
            <span id="progress">0%</span>
        </div>

        <div class="log" id="log">
            กดปุ่ม “เริ่มทำงาน” เพื่อเริ่ม Sync
        </div>

        <!-- ปุ่มขวาล่าง -->
        <div class="action">
            <button id="startBtn">▶ เริ่มทำงาน</button>
        </div>
    </div>

    <script>
        const btn = document.getElementById('startBtn');
        const progress = document.getElementById('progress');
        const logBox = document.getElementById('log');

        btn.addEventListener('click', () => {

            btn.disabled = true;
            btn.classList.remove('ready');
            btn.classList.add('running');
            btn.innerText = '⏳ กำลังทำงาน...';

            fetch('TTNplus_sync_decider.php')
                .then(response => response.body)
                .then(body => {

                    const reader = body.getReader();
                    const decoder = new TextDecoder();

                    function read() {
                        reader.read().then(({
                            done,
                            value
                        }) => {
                            if (done) {
                                logBox.innerHTML += '<br>✅ เสร็จสิ้นทั้งหมด';

                                progress.parentElement.style.setProperty('--percent', '100%');
                                progress.innerText = '100%';

                                btn.disabled = false;
                                btn.classList.remove('running');
                                btn.classList.add('ready');
                                btn.innerText = '↻ ทำงานอีกครั้ง';

                                return;
                            }

                            const lines = decoder.decode(value).trim().split("\n");

                            lines.forEach(line => {
                                if (!line) return;

                                const data = JSON.parse(line);

                                progress.parentElement.style.setProperty('--percent', data.percent + '%');
                                progress.innerText = data.percent + '%';

                                logBox.innerHTML += `
                                    <div class="${data.status.startsWith('ERROR') ? 'err' : 'ok'}">
                                        [${data.done}/${data.total}]
                                        ${data.action} : ${data.tour_id}
                                        → ${data.status}
                                    </div>
                                `;

                                logBox.scrollTop = logBox.scrollHeight;
                            });

                            read();
                        });
                    }
                    read();
                });
        });
    </script>

</body>

</html>