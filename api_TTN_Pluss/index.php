<!DOCTYPE html>
<html lang="th">


<head>
    <meta charset="UTF-8">
    <title>🔄 Tour Data Sync</title>

    <style>
        body {
            font-family: system-ui;
            background: #f4f6f8;
            margin: 0;
            padding: 40px;
        }

        .summary {
            margin-top: 25px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 15px;
        }

        .summary-row span:last-child {
            font-weight: bold;
            color: gray;
        }

        .progress-wrapper {
            margin-top: 20px;
            margin-bottom: 25px;
        }

        .status-text {
            margin-top: 12px;
            font-size: 14px;
            color: #555;
        }

        .footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .card {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        }

        .progress-bar {
            width: 100%;
            height: 35px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #4facfe, #00f2fe);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
            transition: width .3s;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            border: none;
            background: #3498db;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
        }
    </style>
</head>

<body>

    <div class="card">
        <h2>🔄 Tour Data Sync</h2>
        <p>เปรียบเทียบและสร้าง Sync Log</p>

        <div class="progress-wrapper">
            <div class="progress-bar">
                <div class="progress-fill" id="progress">0%</div>
            </div>
        </div>
        <div class="summary">
            <div class="summary-row">
                <span>INSERT</span><span id="sumInsert">0</span>
            </div>
            <div class="summary-row">
                <span>UPDATE</span><span id="sumUpdate">0</span>
            </div>
            <div class="summary-row">
                <span>Soldout</span><span id="sumSoldOut">0</span>
            </div>
            <div class="summary-row">
                <span>SKIP</span><span id="sumSkip">0</span>
            </div>
            <div class="summary-row">
                <span>Log Success</span><span id="sumSuccess">0</span>
            </div>
            <div class="summary-row">
                <span>Log Failed</span><span id="sumFailed">0</span>
            </div>
        </div>

        <div class="footer">
            <button id="runBtn" class="btn" onclick="runSync()">🔄 Run Again</button>
        </div>
    </div>

    <script>
        function runSync() {
            const btn = document.getElementById('runBtn');
            const bar = document.getElementById('progress');

            btn.disabled = true;
            btn.innerText = '⏳ Processing...';

            bar.style.width = '0%';
            bar.innerText = '0%';

            const es = new EventSource('sync_process.php');

            es.onmessage = function(event) {
                const data = JSON.parse(event.data);

                bar.style.width = data.percent + '%';
                bar.innerText = data.percent + '%';

                if (data.done) {
                    es.close();
                    btn.disabled = false;
                    btn.innerText = '🔄 Run Again';

                    // ✅ update summary
                    if (data.summary) {
                        document.getElementById('sumInsert').innerText = data.summary.INSERT;
                        document.getElementById('sumUpdate').innerText = data.summary.UPDATE;
                        document.getElementById('sumSoldOut').innerText = data.summary.SOLDOUT;
                        document.getElementById('sumSkip').innerText = data.summary.SKIP;
                        document.getElementById('sumSuccess').innerText = data.summary.LOG_SUCCESS;
                        document.getElementById('sumFailed').innerText = data.summary.LOG_FAILED;
                    }
                }
            };
        }
    </script>


</body>

</html>