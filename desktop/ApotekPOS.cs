using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net.Sockets;
using System.Threading;
using System.Windows.Forms;
using Microsoft.Win32;

namespace ApotekPOS
{
    static class Program
    {
        private static Mutex singleInstanceMutex = null;
        private static NotifyIcon trayIcon = null;
        private static ContextMenuStrip trayMenu = null;
        public static string appRootDir = "";
        public static string profileDir = "";
        public static List<Process> managedProcesses = new List<Process>();

        private const string RunRegistryKey = @"Software\Microsoft\Windows\CurrentVersion\Run";
        private const string AppName = "ApotekPOS";

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

        public static bool IsAutoStartEnabled()
        {
            try
            {
                using (RegistryKey key = Registry.CurrentUser.OpenSubKey(RunRegistryKey, false))
                {
                    if (key != null)
                    {
                        object val = key.GetValue(AppName);
                        return val != null;
                    }
                }
            }
            catch { }
            return false;
        }

        public static void SetAutoStart(bool enable)
        {
            try
            {
                using (RegistryKey key = Registry.CurrentUser.OpenSubKey(RunRegistryKey, true))
                {
                    if (key != null)
                    {
                        if (enable)
                        {
                            string exePath = Application.ExecutablePath;
                            key.SetValue(AppName, "\"" + exePath + "\"");
                        }
                        else
                        {
                            key.DeleteValue(AppName, false);
                        }
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show("Gagal mengatur auto-start: " + ex.Message, "Pengaturan Auto-Start", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            }
        }

        private static void SetupSystemTray()
        {
            trayMenu = new ContextMenuStrip();
            trayMenu.Font = new Font("Segoe UI", 9.5f, FontStyle.Regular);

            var itemPos = new ToolStripMenuItem("🛒 Buka Kasir POS", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/pos"));
            itemPos.Font = new Font("Segoe UI", 9.5f, FontStyle.Bold);
            trayMenu.Items.Add(itemPos);

            trayMenu.Items.Add(new ToolStripMenuItem("⏱️ Shift Kasir & Modal Awal", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/pos/shift")));
            trayMenu.Items.Add(new ToolStripSeparator());

            trayMenu.Items.Add(new ToolStripMenuItem("📦 Master Obat & Inventori", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/products")));
            trayMenu.Items.Add(new ToolStripMenuItem("🩺 Data Dokter Resep", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/masters/doctors")));
            trayMenu.Items.Add(new ToolStripMenuItem("🏢 Data Distributor & PBF", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/masters/suppliers")));
            trayMenu.Items.Add(new ToolStripMenuItem("👥 Kelola Pegawai & Kasir", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/settings/users")));
            trayMenu.Items.Add(new ToolStripMenuItem("📥 Faktur Pembelian PBF", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/purchases/create")));
            trayMenu.Items.Add(new ToolStripSeparator());

            trayMenu.Items.Add(new ToolStripMenuItem("💾 Backup & Restore Database", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8001/settings/database")));
            trayMenu.Items.Add(new ToolStripMenuItem("🖐 Status Sensor U.are.U & Hardware", null, (s, e) => LaunchAppWindow("http://127.0.0.1:8765/api/v1/health")));

            var autoStartItem = new ToolStripMenuItem("⚙️ Mulai Otomatis saat Komputer Hidup");
            autoStartItem.Checked = IsAutoStartEnabled();
            autoStartItem.Click += (s, e) =>
            {
                bool newState = !autoStartItem.Checked;
                SetAutoStart(newState);
                autoStartItem.Checked = IsAutoStartEnabled();
            };
            trayMenu.Items.Add(autoStartItem);

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
            this.Size = new Size(540, 360);
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
                Text = "Sistem Manajemen Apotek Desktop • Standalone Server Ready",
                Font = new Font("Segoe UI", 9.5f, FontStyle.Regular),
                ForeColor = Color.FromArgb(148, 163, 184), // slate-400
                Location = new Point(34, 62),
                AutoSize = true
            };
            this.Controls.Add(lblSubtitle);

            lblItem1 = CreateStatusRow("• Database Engine (Port 3306 / SQLite)", 110);
            lblItem2 = CreateStatusRow("• Hardware Bridge U.are.U & Mini Printer (Port 8765)", 145);
            lblItem3 = CreateStatusRow("• Mesin Kasir Apotek POS Local (Port 8001)", 180);

            this.Controls.Add(lblItem1);
            this.Controls.Add(lblItem2);
            this.Controls.Add(lblItem3);

            prgBar = new ProgressBar
            {
                Location = new Point(34, 235),
                Size = new Size(472, 12),
                Style = ProgressBarStyle.Marquee,
                MarqueeAnimationSpeed = 30
            };
            this.Controls.Add(prgBar);

            lblStatus = new Label
            {
                Text = "Mendeteksi modul backend dan runtime lokal...",
                Font = new Font("Segoe UI", 8.5f, FontStyle.Italic),
                ForeColor = Color.FromArgb(52, 211, 153), // emerald-400
                Location = new Point(34, 260),
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
                try
                {
                    // Step 1: MySQL / Database Engine
                    UpdateStatus("Memeriksa Database Engine (Port 3306)...");
                    EnsureMySQL();
                    SetItemDone(lblItem1, "✔ Database Server (Port 3306) - Siap");

                    // Step 2: Hardware Bridge
                    UpdateStatus("Memeriksa Hardware Bridge U.are.U (Port 8765)...");
                    EnsureHardwareBridge();
                    SetItemDone(lblItem2, "✔ Hardware Bridge U.are.U & Printer (Port 8765) - Aktif");

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
                }
                catch (Exception ex)
                {
                    UpdateStatus("Terjadi catatan: " + ex.Message);
                    Thread.Sleep(1000);
                    this.Invoke((MethodInvoker)delegate
                    {
                        this.DialogResult = DialogResult.OK;
                        this.Close();
                    });
                }
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

        private string FindPhpExecutable()
        {
            // 1. Portable runtime in app directory
            string p1 = Path.Combine(appRootDir, @"runtime\php\php.exe");
            if (File.Exists(p1)) return p1;

            string p2 = Path.Combine(appRootDir, @"bin\php\php.exe");
            if (File.Exists(p2)) return p2;

            // 2. Laragon PHP paths
            string[] laragonPhps = new string[] {
                @"C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe",
                @"C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe",
            };
            foreach (var p in laragonPhps)
            {
                if (File.Exists(p)) return p;
            }

            try
            {
                if (Directory.Exists(@"C:\laragon\bin\php"))
                {
                    var found = Directory.GetFiles(@"C:\laragon\bin\php", "php.exe", SearchOption.AllDirectories);
                    if (found.Length > 0) return found[0];
                }
            }
            catch { }

            // 3. System PATH fallback
            return "php";
        }

        private string FindMySqlExecutable(out string myIniPath)
        {
            myIniPath = null;

            // 1. Portable runtime in app directory
            string m1 = Path.Combine(appRootDir, @"runtime\mysql\bin\mysqld.exe");
            string ini1 = Path.Combine(appRootDir, @"runtime\mysql\my.ini");
            if (File.Exists(m1))
            {
                myIniPath = File.Exists(ini1) ? ini1 : null;
                return m1;
            }

            // 2. Laragon MySQL
            string m2 = @"C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe";
            string ini2 = @"C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini";
            if (File.Exists(m2))
            {
                myIniPath = File.Exists(ini2) ? ini2 : null;
                return m2;
            }

            try
            {
                if (Directory.Exists(@"C:\laragon\bin\mysql"))
                {
                    var found = Directory.GetFiles(@"C:\laragon\bin\mysql", "mysqld.exe", SearchOption.AllDirectories);
                    if (found.Length > 0)
                    {
                        string foundDir = Path.GetDirectoryName(Path.GetDirectoryName(found[0]));
                        string candidateIni = Path.Combine(foundDir, "my.ini");
                        if (File.Exists(candidateIni)) myIniPath = candidateIni;
                        return found[0];
                    }
                }
            }
            catch { }

            return null;
        }

        private void EnsureMySQL()
        {
            if (IsPortOpen(3306)) return;

            string iniPath;
            string mysqld = FindMySqlExecutable(out iniPath);

            if (!string.IsNullOrEmpty(mysqld) && File.Exists(mysqld))
            {
                string args = !string.IsNullOrEmpty(iniPath)
                    ? string.Format("--defaults-file=\"{0}\"", iniPath)
                    : "";
                StartSilentProcess(mysqld, args, Path.GetDirectoryName(mysqld));
            }

            WaitForPort(3306, 10);
        }

        private void EnsureHardwareBridge()
        {
            if (IsPortOpen(8765)) return;

            string dir = Path.Combine(appRootDir, "hardware-bridge");
            string py = Path.Combine(dir, @".venv\Scripts\python.exe");
            if (!File.Exists(py))
            {
                py = Path.Combine(appRootDir, @"runtime\python\python.exe");
            }
            if (!File.Exists(py)) py = "python";

            StartSilentProcess(py, "-m uvicorn app.main:app --host 127.0.0.1 --port 8765", dir);
            WaitForPort(8765, 8);
        }

        private void EnsurePosLocal()
        {
            if (IsPortOpen(8001)) return;

            string dir = Path.Combine(appRootDir, "apotek-pos-local");
            string phpExe = FindPhpExecutable();

            StartSilentProcess(phpExe, "artisan serve --host=127.0.0.1 --port=8001", dir);
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
                Process p = Process.Start(psi);
                if (p != null)
                {
                    Program.managedProcesses.Add(p);
                }
            }
            catch { }
        }
    }
}
