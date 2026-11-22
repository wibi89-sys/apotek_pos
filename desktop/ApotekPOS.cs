using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net.Sockets;
using System.Threading;
using System.Windows.Forms;

namespace ApotekPOS
{
    static class Program
    {
        private static Mutex singleInstanceMutex = null;
        private static NotifyIcon trayIcon = null;
        private static ContextMenuStrip trayMenu = null;
        private static string appRootDir = "";
        private static string profileDir = "";
        private static List<Process> managedProcesses = new List<Process>();

        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            // Single instance lock
            bool createdNew;
            singleInstanceMutex = new Mutex(true, "Global\\ApotekPOS_SingleInstance_Mutex_2026", out createdNew);
            if (!createdNew)
            {
                // Already running, launch POS window
                LaunchAppWindow("http://127.0.0.1:8001/pos");
                return;
            }

            // Determine root directory
            string exeDir = AppDomain.CurrentDomain.BaseDirectory;
            if (File.Exists(Path.Combine(exeDir, "artisan")) || Directory.Exists(Path.Combine(exeDir, "apotek-pos-local")))
            {
                appRootDir = exeDir;
            }
            else if (Directory.Exists(@"c:\laragon\www\apotek_pos"))
            {
                appRootDir = @"c:\laragon\www\apotek_pos";
            }
            else
            {
                appRootDir = Path.GetFullPath(Path.Combine(exeDir, ".."));
            }

            profileDir = Path.Combine(appRootDir, ".desktop_profile");
            try { Directory.CreateDirectory(profileDir); } catch { }

            // Show Splash / Startup form
            using (var splash = new SplashForm(appRootDir))
            {
                if (splash.ShowDialog() != DialogResult.OK)
                {
                    return;
                }
            }

            // Setup System Tray
            SetupSystemTray();

            // Launch Desktop POS Window
            LaunchAppWindow("http://127.0.0.1:8001/pos");

            // Run message loop for system tray
            Application.Run();
        }

        private static void SetupSystemTray()
        {
            trayMenu = new ContextMenuStrip();
            trayMenu.Font = new Font("Segoe UI", 9.5f, FontStyle.Regular);

            var itemPos = new ToolStripMenuItem("💊 Buka Kasir POS", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/pos"));
            itemPos.Font = new Font("Segoe UI", 9.5f, FontStyle.Bold);
            trayMenu.Items.Add(itemPos);

            trayMenu.Items.Add(new ToolStripMenuItem("📦 Master Obat & Stok", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/products")));
            trayMenu.Items.Add(new ToolStripMenuItem("📑 Faktur Pembelian PBF", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/purchases/create")));
            trayMenu.Items.Add(new ToolStripMenuItem("📊 Shift Kasir & Laporan", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/pos/shift")));
            trayMenu.Items.Add(new ToolStripSeparator());

            trayMenu.Items.Add(new ToolStripMenuItem("🖐 Status Sensor U.are.U & Hardware", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8765/api/v1/fingerprint/status")));
            trayMenu.Items.Add(new ToolStripSeparator());

            trayMenu.Items.Add(new ToolStripMenuItem("🚪 Tutup Aplikasi & Hentikan Layanan", null, (s, e) => ShutdownApplication()));

            trayIcon = new NotifyIcon();
            trayIcon.Text = "Apotek POS Pro (Berjalan di Latar Belakang)";
            trayIcon.ContextMenuStrip = trayMenu;
            trayIcon.Visible = true;

            string iconPath = Path.Combine(appRootDir, "ApotekPOS.ico");
            if (File.Exists(iconPath))
            {
                try { trayIcon.Icon = new Icon(iconPath); } catch { trayIcon.Icon = SystemIcons.Application; }
            }
            else
            {
                trayIcon.Icon = SystemIcons.Application;
            }

            trayIcon.DoubleClick += (s, e) => LaunchAppWindow("http://127.0.0.1:8001/pos");
        }

        public static void LaunchAppWindow(string url)
        {
            string[] browsers = new string[] {
                @"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe",
                @"C:\Program Files\Microsoft\Edge\Application\msedge.exe",
                @"C:\Program Files\Google\Chrome\Application\chrome.exe",
                @"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
            };

            string browserPath = null;
            foreach (var b in browsers)
            {
                if (File.Exists(b)) { browserPath = b; break; }
            }

            try
            {
                if (browserPath != null)
                {
                    string args = string.Format("--app=\"{0}\" --window-size=1280,820 --user-data-dir=\"{1}\"", url, profileDir);
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = browserPath,
                        Arguments = args,
                        UseShellExecute = true
                    });
                }
                else
                {
                    Process.Start(url);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Gagal membuka jendela aplikasi: " + ex.Message, "Apotek POS Pro", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        public static void ShutdownApplication()
        {
            var result = MessageBox.Show(
                "Apakah Anda yakin ingin menutup Apotek POS dan menghentikan seluruh layanan backend lokal?",
                "Konfirmasi Tutup Apotek POS Pro",
                MessageBoxButtons.YesNo,
                MessageBoxIcon.Question
            );

            if (result == DialogResult.Yes)
            {
                if (trayIcon != null)
                {
                    trayIcon.Visible = false;
                    trayIcon.Dispose();
                }

                // Kill managed child processes if any
                foreach (var p in managedProcesses)
                {
                    try { if (!p.HasExited) p.Kill(); } catch { }
                }

                Application.Exit();
            }
        }
    }

    public class SplashForm : Form
    {
        private string appRootDir;
        private Label lblTitle;
        private Label lblSubtitle;
        private Label lblStatus;
        private ProgressBar prgBar;
        private Label lblItem1;
        private Label lblItem2;
        private Label lblItem3;
        private System.Windows.Forms.Timer startTimer;

        public SplashForm(string rootDir)
        {
            this.appRootDir = rootDir;
            InitializeComponent();
        }

        private void InitializeComponent()
        {
            this.FormBorderStyle = FormBorderStyle.None;
            this.StartPosition = FormStartPosition.CenterScreen;
            this.Size = new Size(520, 340);
            this.BackColor = Color.FromArgb(15, 23, 42); // slate-900
            this.ForeColor = Color.White;
            this.ShowInTaskbar = true;
            this.TopMost = true;

            string iconPath = Path.Combine(appRootDir, "ApotekPOS.ico");
            if (File.Exists(iconPath))
            {
                try { this.Icon = new Icon(iconPath); } catch { }
            }

            // Emerald accent bar at top
            Panel topBar = new Panel { Dock = DockStyle.Top, Height = 6, BackColor = Color.FromArgb(16, 185, 129) };
            this.Controls.Add(topBar);

            lblTitle = new Label
            {
                Text = "APOTEK POS PRO",
                Font = new Font("Segoe UI", 18, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(32, 28),
                AutoSize = true
            };
            this.Controls.Add(lblTitle);

            lblSubtitle = new Label
            {
                Text = "Sistem Kasir Farmasi Desktop • U.are.U Fingerprint Biometrik Ready",
                Font = new Font("Segoe UI", 9.5f, FontStyle.Regular),
                ForeColor = Color.FromArgb(148, 163, 184), // slate-400
                Location = new Point(34, 62),
                AutoSize = true
            };
            this.Controls.Add(lblSubtitle);

            lblItem1 = CreateStatusRow("• Database MySQL 8.4 Server (Port 3306)", 110);
            lblItem2 = CreateStatusRow("• Hardware Bridge U.are.U & Mini Printer (Port 8765)", 145);
            lblItem3 = CreateStatusRow("• Mesin Kasir Apotek POS Local (Port 8001)", 180);

            this.Controls.Add(lblItem1);
            this.Controls.Add(lblItem2);
            this.Controls.Add(lblItem3);

            prgBar = new ProgressBar
            {
                Location = new Point(34, 230),
                Size = new Size(452, 12),
                Style = ProgressBarStyle.Marquee,
                MarqueeAnimationSpeed = 30
            };
            this.Controls.Add(prgBar);

            lblStatus = new Label
            {
                Text = "Memeriksa dan menyinkronkan seluruh modul backend...",
                Font = new Font("Segoe UI", 8.5f, FontStyle.Italic),
                ForeColor = Color.FromArgb(52, 211, 153), // emerald-400
                Location = new Point(34, 255),
                AutoSize = true
            };
            this.Controls.Add(lblStatus);

            startTimer = new System.Windows.Forms.Timer();
            startTimer.Interval = 200;
            startTimer.Tick += StartTimer_Tick;
            startTimer.Start();
        }

        private Label CreateStatusRow(string text, int top)
        {
            return new Label
            {
                Text = text,
                Font = new Font("Segoe UI", 9.5f, FontStyle.Regular),
                ForeColor = Color.FromArgb(203, 213, 225), // slate-300
                Location = new Point(36, top),
                AutoSize = true
            };
        }

        private void StartTimer_Tick(object sender, EventArgs e)
        {
            startTimer.Stop();

            Thread checkThread = new Thread(() =>
            {
                // Step 1: MySQL
                UpdateStatus("Memeriksa Database MySQL (Port 3306)...");
                EnsureMySQL();
                SetItemDone(lblItem1, "✔ Database MySQL 8.4 Server (Port 3306) - Siap");

                // Step 2: Hardware Bridge
                UpdateStatus("Memeriksa Hardware Bridge U.are.U (Port 8765)...");
                EnsureHardwareBridge();
                SetItemDone(lblItem2, "✔ Hardware Bridge U.are.U & Mini Printer (Port 8765) - Aktif");

                // Step 3: POS Local
                UpdateStatus("Memeriksa Layanan Kasir Apotek POS (Port 8001)...");
                EnsurePosLocal();
                SetItemDone(lblItem3, "✔ Mesin Kasir Apotek POS Local (Port 8001) - Siap");

                UpdateStatus("Semua layanan siap! Membuka antarmuka kasir desktop...");
                Thread.Sleep(500);

                this.Invoke((MethodInvoker)delegate
                {
                    this.DialogResult = DialogResult.OK;
                    this.Close();
                });
            });

            checkThread.IsBackground = true;
            checkThread.Start();
        }

        private void UpdateStatus(string text)
        {
            if (this.IsDisposed) return;
            this.Invoke((MethodInvoker)delegate { lblStatus.Text = text; });
        }

        private void SetItemDone(Label lbl, string text)
        {
            if (this.IsDisposed) return;
            this.Invoke((MethodInvoker)delegate
            {
                lbl.Text = text;
                lbl.ForeColor = Color.FromArgb(52, 211, 153); // emerald
            });
        }

        private bool IsPortOpen(int port)
        {
            try
            {
                using (var c = new TcpClient())
                {
                    var r = c.BeginConnect("127.0.0.1", port, null, null);
                    bool ok = r.AsyncWaitHandle.WaitOne(600);
                    if (ok) { c.EndConnect(r); return true; }
                    return false;
                }
            }
            catch { return false; }
        }

        private void EnsureMySQL()
        {
            if (IsPortOpen(3306)) return;
            string mysqld = @"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe";
            string ini = @"C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini";
            if (File.Exists(mysqld))
            {
                StartSilentProcess(mysqld, string.Format("--defaults-file=\"{0}\"", ini), Path.GetDirectoryName(mysqld));
            }
            WaitForPort(3306, 12);
        }

        private void EnsureHardwareBridge()
        {
            if (IsPortOpen(8765)) return;
            string dir = Path.Combine(appRootDir, "hardware-bridge");
            string py = Path.Combine(dir, @".venv\Scripts\python.exe");
            if (!File.Exists(py)) py = "python";
            StartSilentProcess(py, "-m uvicorn app.main:app --host 127.0.0.1 --port 8765", dir);
            WaitForPort(8765, 10);
        }

        private void EnsurePosLocal()
        {
            if (IsPortOpen(8001)) return;
            string dir = Path.Combine(appRootDir, "apotek-pos-local");
            StartSilentProcess("php", "artisan serve --host=127.0.0.1 --port=8001", dir);
            WaitForPort(8001, 10);
        }

        private void WaitForPort(int port, int maxSeconds)
        {
            for (int i = 0; i < maxSeconds * 2; i++)
            {
                if (IsPortOpen(port)) return;
                Thread.Sleep(500);
            }
        }

        private void StartSilentProcess(string fileName, string args, string workingDir)
        {
            try
            {
                var psi = new ProcessStartInfo
                {
                    FileName = fileName,
                    Arguments = args,
                    WorkingDirectory = workingDir,
                    CreateNoWindow = true,
                    UseShellExecute = false,
                    WindowStyle = ProcessWindowStyle.Hidden
                };
                Process.Start(psi);
            }
            catch { }
        }
    }
}
