#include "phpx.h"

#include <QApplication>
#include <QCheckBox>
#include <QComboBox>
#include <QColor>
#include <QDialog>
#include <QDialogButtonBox>
#include <QDateTime>
#include <QEventLoop>
#include <QFileDialog>
#include <QFont>
#include <QFormLayout>
#include <QGroupBox>
#include <QHBoxLayout>
#include <QHeaderView>
#include <QIcon>
#include <QLabel>
#include <QLineEdit>
#include <QMainWindow>
#include <QMessageBox>
#include <QPlainTextEdit>
#include <QProcess>
#include <QPushButton>
#include <QSet>
#include <QSignalBlocker>
#include <QSpinBox>
#include <QSplitter>
#include <QStatusBar>
#include <QStringList>
#include <QTableWidget>
#include <QTextCursor>
#include <QTimer>
#include <QVBoxLayout>

#include <deque>

using php::Array;
using php::Bool;
using php::Box;
using php::String;
using php::var;
using php::Variant;

namespace {

static int qt_argc = 1;
static char qt_program_name[] = "typephp-ssh-tunnel-manager";
static char *qt_argv[] = {qt_program_name, nullptr};
static QApplication *qt_application = nullptr;

QIcon applicationIcon() {
    QIcon icon;
    icon.addFile(":/icons/icon_16.png");
    icon.addFile(":/icons/icon_32.png");
    icon.addFile(":/icons/icon_64.png");
    icon.addFile(":/icons/icon_128.png");
    icon.addFile(":/icons/icon_256.png");
    icon.addFile(":/icons/icon_512.png");
    icon.addFile(":/icons/icon_1024.png");
    return icon;
}

QString toQString(const Variant &value) {
    if (value.isNull() || value.isUndef()) {
        return {};
    }
    return QString::fromUtf8(value.toCString());
}

String toPhpString(const QString &value) {
    const QByteArray utf8 = value.toUtf8();
    return String(utf8.constData(), static_cast<size_t>(utf8.size()));
}

struct RuleForm {
    QString id;
    QString name;
    QString type = "local";
    QString sshHost;
    int sshPort = 22;
    QString sshUser;
    QString identityFile;
    bool debug = false;
    QString localHost = "127.0.0.1";
    int localPort = 1080;
    QString remoteHost = "127.0.0.1";
    int remotePort = 80;
};

RuleForm fromPhpRule(const Array &rule) {
    RuleForm result;
    result.id = toQString(rule.get("id"));
    result.name = toQString(rule.get("name"));
    result.type = toQString(rule.get("type"));
    result.sshHost = toQString(rule.get("ssh_host"));
    result.sshPort = static_cast<int>(rule.get("ssh_port").toInt());
    result.sshUser = toQString(rule.get("ssh_user"));
    result.identityFile = toQString(rule.get("identity_file"));
    const Variant debug = rule.get("debug");
    result.debug = !debug.isNull() && !debug.isUndef() && debug.toBool();
    result.localHost = toQString(rule.get("local_host"));
    result.localPort = static_cast<int>(rule.get("local_port").toInt());
    result.remoteHost = toQString(rule.get("remote_host"));
    result.remotePort = static_cast<int>(rule.get("remote_port").toInt());
    return result;
}

Array toPhpRule(const RuleForm &rule) {
    Array result;
    result.set("id", toPhpString(rule.id));
    result.set("name", toPhpString(rule.name));
    result.set("type", toPhpString(rule.type));
    result.set("ssh_host", toPhpString(rule.sshHost));
    result.set("ssh_port", rule.sshPort);
    result.set("ssh_user", toPhpString(rule.sshUser));
    result.set("identity_file", toPhpString(rule.identityFile));
    result.set("debug", rule.debug);
    result.set("local_host", toPhpString(rule.localHost));
    result.set("local_port", rule.localPort);
    result.set("remote_host", toPhpString(rule.remoteHost));
    result.set("remote_port", rule.remotePort);
    return result;
}

class RuleDialog final : public QDialog {
  public:
    explicit RuleDialog(QWidget *parent, const RuleForm &initial) : QDialog(parent) {
        setWindowTitle(initial.id.isEmpty() ? tr("新建 SSH 隧道") : tr("编辑 SSH 隧道"));
        setMinimumWidth(520);

        name_ = new QLineEdit(initial.name);
        type_ = new QComboBox();
        type_->addItem(tr("服务器端口映射为本地端口"), "local");
        type_->addItem(tr("本地端口映射为服务器端口"), "remote");
        type_->addItem(tr("服务器作为本地 SOCKS5 代理"), "socks5");
        const int typeIndex = type_->findData(initial.type);
        type_->setCurrentIndex(typeIndex < 0 ? 0 : typeIndex);

        sshHost_ = new QLineEdit(initial.sshHost);
        sshPort_ = portSpin(initial.sshPort);
        sshUser_ = new QLineEdit(initial.sshUser);
        identityFile_ = new QLineEdit(initial.identityFile);
        debug_ = new QCheckBox(tr("输出 OpenSSH 调试信息"));
        debug_->setChecked(initial.debug);
        auto *identityBrowse = new QPushButton(tr("浏览…"));
        auto *identityRow = new QWidget();
        auto *identityLayout = new QHBoxLayout(identityRow);
        identityLayout->setContentsMargins(0, 0, 0, 0);
        identityLayout->addWidget(identityFile_);
        identityLayout->addWidget(identityBrowse);

        localHost_ = new QLineEdit(initial.localHost);
        localPort_ = portSpin(initial.localPort);
        remoteHost_ = new QLineEdit(initial.remoteHost);
        remotePort_ = portSpin(initial.remotePort);

        // QLineEdit enables input methods by default, but setting the
        // attribute explicitly is important when this widget is hosted by an
        // embedded PHP runtime instead of QApplication::exec().
        const QList<QLineEdit *> textInputs = {name_, sshHost_, sshUser_, identityFile_, localHost_, remoteHost_};
        for (QLineEdit *input : textInputs) {
            input->setAttribute(Qt::WA_InputMethodEnabled, true);
            input->setInputMethodHints(Qt::ImhNone);
        }

        auto *form = new QFormLayout();
        form->addRow(tr("规则名称"), name_);
        form->addRow(tr("映射类型"), type_);
        form->addRow(tr("SSH 服务器"), sshHost_);
        form->addRow(tr("SSH 端口"), sshPort_);
        form->addRow(tr("SSH 用户"), sshUser_);
        form->addRow(tr("私钥文件"), identityRow);
        form->addRow(tr("调试"), debug_);
        form->addRow(tr("本机地址"), localHost_);
        form->addRow(tr("本机端口"), localPort_);
        form->addRow(tr("远程地址"), remoteHost_);
        form->addRow(tr("远程端口"), remotePort_);

        auto *buttons = new QDialogButtonBox(QDialogButtonBox::Save | QDialogButtonBox::Cancel);
        connect(buttons, &QDialogButtonBox::accepted, this, &QDialog::accept);
        connect(buttons, &QDialogButtonBox::rejected, this, &QDialog::reject);
        connect(identityBrowse, &QPushButton::clicked, this, [this]() {
            const QString path = QFileDialog::getOpenFileName(this, tr("选择 SSH 私钥"), identityFile_->text());
            if (!path.isEmpty()) {
                identityFile_->setText(path);
            }
        });
        connect(
            type_, QOverload<int>::of(&QComboBox::currentIndexChanged), this, [this](int) { updateTargetFields(); });

        auto *layout = new QVBoxLayout(this);
        layout->addLayout(form);
        layout->addWidget(buttons);
        updateTargetFields();
    }

    RuleForm value(const QString &id) const {
        RuleForm result;
        result.id = id;
        result.name = name_->text().trimmed();
        result.type = type_->currentData().toString();
        result.sshHost = sshHost_->text().trimmed();
        result.sshPort = sshPort_->value();
        result.sshUser = sshUser_->text().trimmed();
        result.identityFile = identityFile_->text().trimmed();
        result.debug = debug_->isChecked();
        result.localHost = localHost_->text().trimmed();
        result.localPort = localPort_->value();
        result.remoteHost = remoteHost_->text().trimmed();
        result.remotePort = remotePort_->value();
        return result;
    }

  private:
    static QSpinBox *portSpin(int value) {
        auto *spin = new QSpinBox();
        spin->setRange(1, 65535);
        spin->setValue(value > 0 ? value : 1);
        return spin;
    }

    void updateTargetFields() {
        const bool enabled = type_->currentData().toString() != "socks5";
        remoteHost_->setEnabled(enabled);
        remotePort_->setEnabled(enabled);
    }

    QLineEdit *name_;
    QComboBox *type_;
    QLineEdit *sshHost_;
    QSpinBox *sshPort_;
    QLineEdit *sshUser_;
    QLineEdit *identityFile_;
    QCheckBox *debug_;
    QLineEdit *localHost_;
    QSpinBox *localPort_;
    QLineEdit *remoteHost_;
    QSpinBox *remotePort_;
};

class TunnelWindowBox final : public Box {
  public:
    static QString tr(const char *text) {
        return QObject::tr(text);
    }

    explicit TunnelWindowBox(const QString &title) {
        window_ = new QMainWindow();
        window_->setWindowTitle(title);
        window_->setWindowIcon(QApplication::windowIcon());
        window_->resize(1120, 720);

        auto *central = new QWidget();
        auto *layout = new QVBoxLayout(central);
        auto *titleLabel = new QLabel(tr("<h2>SSH Tunnel Manager</h2>"
                                         "<p>规则和 SSH 参数由 TypePHP 管理，Qt 仅提供界面与进程桥接。</p>"));
        layout->addWidget(titleLabel);

        table_ = new QTableWidget(0, 6);
        table_->setHorizontalHeaderLabels(
            {tr("名称"), tr("类型"), tr("本机地址"), tr("远程地址"), tr("SSH 服务器"), tr("状态")});
        table_->setSelectionBehavior(QAbstractItemView::SelectRows);
        table_->setSelectionMode(QAbstractItemView::SingleSelection);
        table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
        table_->verticalHeader()->setVisible(false);
        // Interactive mode is required for resizing with the header handles.
        // ResizeToContents and Stretch would continuously overwrite widths
        // chosen by the user when TypePHP refreshes the rows.
        table_->horizontalHeader()->setSectionResizeMode(QHeaderView::Interactive);
        table_->horizontalHeader()->setSectionsMovable(true);
        table_->horizontalHeader()->setStretchLastSection(false);
        table_->horizontalHeader()->setMinimumSectionSize(72);
        table_->setColumnWidth(0, 170);
        table_->setColumnWidth(1, 210);
        table_->setColumnWidth(2, 190);
        table_->setColumnWidth(3, 190);
        table_->setColumnWidth(4, 220);
        table_->setColumnWidth(5, 72);

        log_ = new QPlainTextEdit();
        log_->setReadOnly(true);
        log_->setMaximumBlockCount(500);
        log_->setPlaceholderText(tr("选择一条隧道后显示其日志"));
        auto *logGroup = new QGroupBox(tr("所选隧道日志"));
        auto *logLayout = new QVBoxLayout(logGroup);
        logLayout->setContentsMargins(6, 10, 6, 6);
        clearLogButton_ = new QPushButton(tr("清理日志"));
        clearLogButton_->setEnabled(false);
        logLayout->addWidget(log_);
        auto *splitter = new QSplitter(Qt::Vertical);
        splitter->addWidget(table_);
        splitter->addWidget(logGroup);
        splitter->setStretchFactor(0, 4);
        splitter->setStretchFactor(1, 1);
        layout->addWidget(splitter, 1);

        auto *add = new QPushButton(tr("新建"));
        auto *edit = new QPushButton(tr("编辑"));
        auto *remove = new QPushButton(tr("删除"));
        startButton_ = new QPushButton(tr("启动"));
        stopButton_ = new QPushButton(tr("停止"));
        startButton_->setEnabled(false);
        stopButton_->setEnabled(false);
        auto *buttons = new QHBoxLayout();
        buttons->addWidget(add);
        buttons->addWidget(edit);
        buttons->addWidget(remove);
        buttons->addWidget(clearLogButton_);
        buttons->addStretch();
        buttons->addWidget(startButton_);
        buttons->addWidget(stopButton_);
        layout->addLayout(buttons);

        window_->setCentralWidget(central);
        window_->statusBar()->showMessage(tr("就绪"));

        QObject::connect(add, &QPushButton::clicked, window_, [this]() { openCreateDialog(); });
        QObject::connect(edit, &QPushButton::clicked, window_, [this]() { openEditDialog(); });
        QObject::connect(remove, &QPushButton::clicked, window_, [this]() {
            const QString id = selectedId();
            if (id.isEmpty()) {
                showSelectionRequired();
                return;
            }
            if (QMessageBox::question(window_, tr("删除规则"), tr("确认删除选中的隧道规则？")) == QMessageBox::Yes) {
                enqueue("delete", id);
            }
        });
        QObject::connect(startButton_, &QPushButton::clicked, window_, [this]() {
            const QString id = selectedId();
            if (id.isEmpty()) {
                showSelectionRequired();
            } else {
                startButton_->setEnabled(false);
                enqueue("start", id);
            }
        });
        QObject::connect(stopButton_, &QPushButton::clicked, window_, [this]() {
            const QString id = selectedId();
            if (id.isEmpty()) {
                showSelectionRequired();
            } else {
                startButton_->setEnabled(false);
                stopButton_->setEnabled(false);
                enqueue("stop", id);
            }
        });
        QObject::connect(clearLogButton_, &QPushButton::clicked, window_, [this]() {
            const QString id = selectedId();
            if (id.isEmpty()) {
                return;
            }
            logs_.remove(id);
            log_->clear();
            log_->setPlaceholderText(tr("该隧道暂时没有日志"));
            clearLogButton_->setEnabled(false);
        });
        QObject::connect(table_, &QTableWidget::cellDoubleClicked, window_, [this](int, int) { openEditDialog(); });
        QObject::connect(table_, &QTableWidget::itemSelectionChanged, window_, [this]() {
            renderSelectedLog();
            updateActionButtons();
        });
        QObject::connect(window_, &QObject::destroyed, [this]() { window_ = nullptr; });

        window_->show();
    }

    ~TunnelWindowBox() override {
        cleanup();
    }

    bool isOpen() const {
        return window_ != nullptr && window_->isVisible();
    }

    void processEvents() {
        if (qt_application) {
            // A short real Qt event loop keeps platform input-context socket
            // notifiers alive (IBus/Fcitx on Linux and TSF/IMM on Windows).
            // A one-shot processEvents() followed by PHP usleep() is not
            // sufficient for reliable pre-edit/composition delivery.
            QEventLoop eventLoop;
            QTimer::singleShot(16, &eventLoop, &QEventLoop::quit);
            eventLoop.exec(QEventLoop::AllEvents);
        }
    }

    Array pollEvent() {
        if (events_.empty()) {
            return {};
        }
        Array result = events_.front();
        events_.pop_front();
        return result;
    }

    void setRules(const Array &rules) {
        const QString previousSelection = selectedId();
        const QSignalBlocker selectionBlocker(table_);
        rules_.clear();
        statuses_.clear();
        table_->setRowCount(0);
        int restoredRow = -1;
        for (size_t index = 0; index < rules.count(); ++index) {
            const Array row = rules.get(index).toArray();
            const RuleForm rule = fromPhpRule(row);
            rules_.insert(rule.id, rule);
            statuses_.insert(rule.id, toQString(row.get("status")));

            const int tableRow = table_->rowCount();
            table_->insertRow(tableRow);
            setCell(tableRow, 0, rule.name);
            table_->item(tableRow, 0)->setData(Qt::UserRole, rule.id);
            setCell(tableRow, 1, toQString(row.get("type_label")));
            setCell(tableRow, 2, toQString(row.get("local_address_label")));
            setCell(tableRow, 3, toQString(row.get("remote_address_label")));
            setCell(tableRow, 4, toQString(row.get("server_label")));
            setStatusCell(tableRow, 5, toQString(row.get("status")));
            if (rule.id == previousSelection) {
                restoredRow = tableRow;
            }
        }
        if (restoredRow >= 0) {
            table_->selectRow(restoredRow);
        }
        renderSelectedLog();
        updateActionButtons();
    }

    bool startProcess(const QString &id, const QString &program, const Array &arguments) {
        if (processes_.contains(id)) {
            QProcess *existing = processes_.value(id);
            if (existing && existing->state() != QProcess::NotRunning) {
                return true;
            }
            processes_.remove(id);
            processOutputBuffers_.remove(id);
            if (existing) {
                existing->deleteLater();
            }
        }

        QStringList args;
        for (size_t index = 0; index < arguments.count(); ++index) {
            args.append(toQString(arguments.get(index)));
        }

        auto *process = new QProcess(window_);
        process->setProcessChannelMode(QProcess::MergedChannels);
        processes_.insert(id, process);
        QObject::connect(process, &QProcess::started, window_, [this, id]() { enqueue("process_started", id); });
        QObject::connect(process, &QProcess::readyRead, window_, [this, id, process]() {
            enqueueProcessOutput(id, process, false);
        });
        QObject::connect(process,
                         QOverload<int, QProcess::ExitStatus>::of(&QProcess::finished),
                         window_,
                         [this, id, process](int exitCode, QProcess::ExitStatus status) {
                             enqueueProcessOutput(id, process, true);
                             const bool requestedStop = stopping_.remove(id);
                             if (!requestedStop && (status == QProcess::CrashExit || exitCode != 0)) {
                                 enqueue("process_error", id, tr("ssh 已退出，退出码 %1").arg(exitCode));
                             } else {
                                 enqueue("process_stopped", id);
                             }
                             processes_.remove(id);
                             process->deleteLater();
                         });
        QObject::connect(process, &QProcess::errorOccurred, window_, [this, id, process](QProcess::ProcessError error) {
            if (error == QProcess::FailedToStart) {
                enqueue("process_error", id, tr("无法启动 ssh，请检查 OpenSSH 客户端是否位于 PATH"));
                processes_.remove(id);
                process->deleteLater();
            }
        });
        process->start(program, args);
        return true;
    }

    void stopProcess(const QString &id) {
        QProcess *process = processes_.value(id, nullptr);
        if (!process || process->state() == QProcess::NotRunning) {
            enqueue("process_stopped", id);
            return;
        }
        stopping_.insert(id);
        process->terminate();
        if (!process->waitForFinished(1500)) {
            process->kill();
        }
    }

    void appendLog(const QString &id, const QString &message) {
        QStringList &entries = logs_[id];
        const QString entry = QString("[%1] %2").arg(QDateTime::currentDateTime().toString("HH:mm:ss"), message);
        entries.append(entry);
        while (entries.size() > 500) {
            entries.removeFirst();
        }

        if (selectedId() != id) {
            return;
        }
        clearLogButton_->setEnabled(true);
        if (displayedLogId_ != id) {
            renderSelectedLog();
            return;
        }
        log_->appendPlainText(entry);
        log_->moveCursor(QTextCursor::End);
    }

    void showError(const QString &message) {
        QMessageBox::critical(window_, tr("SSH Tunnel Manager"), message);
    }

    void cleanup() {
        const QList<QProcess *> processList = processes_.values();
        processes_.clear();
        for (QProcess *process : processList) {
            if (process && process->state() != QProcess::NotRunning) {
                process->terminate();
                if (!process->waitForFinished(500)) {
                    process->kill();
                    process->waitForFinished(500);
                }
            }
        }
        stopping_.clear();
        if (window_) {
            delete window_;
            window_ = nullptr;
        }
    }

  private:
    static QString statusLabel(const QString &status) {
        if (status == "running") {
            return QObject::tr("运行中");
        }
        if (status == "starting") {
            return QObject::tr("启动中");
        }
        if (status == "stopping") {
            return QObject::tr("停止中");
        }
        if (status == "error") {
            return QObject::tr("错误");
        }
        return QObject::tr("已停止");
    }

    void setCell(int row, int column, const QString &text) {
        table_->setItem(row, column, new QTableWidgetItem(text));
    }

    void setStatusCell(int row, int column, const QString &status) {
        auto *item = new QTableWidgetItem(QStringLiteral("●"));
        item->setTextAlignment(Qt::AlignCenter);
        QFont font = item->font();
        font.setPointSize(16);
        item->setFont(font);

        if (status == "running") {
            item->setForeground(QColor("#22c55e"));
        } else if (status == "starting" || status == "stopping") {
            item->setForeground(QColor("#f59e0b"));
        } else {
            item->setForeground(QColor("#ef4444"));
        }
        item->setToolTip(statusLabel(status));
        table_->setItem(row, column, item);
    }

    QString selectedId() const {
        const auto selection = table_->selectionModel()->selectedRows();
        if (selection.isEmpty()) {
            return {};
        }
        QTableWidgetItem *item = table_->item(selection.first().row(), 0);
        return item ? item->data(Qt::UserRole).toString() : QString();
    }

    void renderSelectedLog() {
        const QString id = selectedId();
        if (id == displayedLogId_) {
            return;
        }
        displayedLogId_ = id;
        log_->clear();
        if (id.isEmpty()) {
            log_->setPlaceholderText(tr("选择一条隧道后显示其日志"));
            return;
        }
        log_->setPlaceholderText(tr("该隧道暂时没有日志"));
        const auto entries = logs_.constFind(id);
        if (entries != logs_.constEnd() && !entries->isEmpty()) {
            log_->setPlainText(entries->join('\n'));
            log_->moveCursor(QTextCursor::End);
        }
    }

    void updateActionButtons() {
        const QString id = selectedId();
        if (id.isEmpty()) {
            startButton_->setEnabled(false);
            stopButton_->setEnabled(false);
            clearLogButton_->setEnabled(false);
            return;
        }

        const auto logEntries = logs_.constFind(id);
        clearLogButton_->setEnabled(logEntries != logs_.constEnd() && !logEntries->isEmpty());
        const QString status = statuses_.value(id, "stopped");
        if (status == "running" || status == "starting") {
            startButton_->setEnabled(false);
            stopButton_->setEnabled(true);
        } else if (status == "stopping") {
            startButton_->setEnabled(false);
            stopButton_->setEnabled(false);
        } else {
            startButton_->setEnabled(true);
            stopButton_->setEnabled(false);
        }
    }

    void showSelectionRequired() {
        QMessageBox::information(window_, tr("SSH Tunnel Manager"), tr("请先选择一条规则"));
    }

    void openCreateDialog() {
        RuleDialog dialog(window_, RuleForm{});
        if (dialog.exec() == QDialog::Accepted) {
            enqueue("create", {}, {}, toPhpRule(dialog.value({})));
        }
    }

    void openEditDialog() {
        const QString id = selectedId();
        if (id.isEmpty()) {
            showSelectionRequired();
            return;
        }
        RuleDialog dialog(window_, rules_.value(id));
        if (dialog.exec() == QDialog::Accepted) {
            enqueue("update", id, {}, toPhpRule(dialog.value(id)));
        }
    }

    void enqueue(const QString &type, const QString &id = {}, const QString &message = {}, const Array &payload = {}) {
        Array event;
        event.set("type", toPhpString(type));
        if (!id.isEmpty()) {
            event.set("id", toPhpString(id));
        }
        if (!message.isEmpty()) {
            event.set("message", toPhpString(message));
        }
        if (payload.count() > 0) {
            event.set("payload", payload);
        }
        events_.push_back(event);
    }

    void enqueueProcessOutput(const QString &id, QProcess *process, bool flush) {
        QByteArray &pending = processOutputBuffers_[id];
        pending.append(process->readAll());
        qsizetype newline = -1;
        while ((newline = pending.indexOf('\n')) >= 0) {
            QByteArray line = pending.left(newline);
            pending.remove(0, newline + 1);
            if (line.endsWith('\r')) {
                line.chop(1);
            }
            if (!line.isEmpty()) {
                enqueue("process_output", id, QString::fromUtf8(line));
            }
        }
        if (flush) {
            if (!pending.isEmpty()) {
                enqueue("process_output", id, QString::fromUtf8(pending));
            }
            processOutputBuffers_.remove(id);
        }
    }

    QMainWindow *window_ = nullptr;
    QTableWidget *table_ = nullptr;
    QPlainTextEdit *log_ = nullptr;
    QPushButton *startButton_ = nullptr;
    QPushButton *stopButton_ = nullptr;
    QPushButton *clearLogButton_ = nullptr;
    QHash<QString, RuleForm> rules_;
    QHash<QString, QString> statuses_;
    QHash<QString, QProcess *> processes_;
    QHash<QString, QByteArray> processOutputBuffers_;
    QHash<QString, QStringList> logs_;
    QString displayedLogId_;
    QSet<QString> stopping_;
    std::deque<Array> events_;
};

TunnelWindowBox *windowBox(var box) {
    return box.toBox<TunnelWindowBox>();
}

}  // namespace

var php_qt_tunnel_create(String title) {
    if (!qt_application) {
        qt_application = new QApplication(qt_argc, qt_argv);
        qt_application->setApplicationName("TypePHP SSH Tunnel Manager");
        qt_application->setOrganizationName("TypePHP");
        qt_application->setWindowIcon(applicationIcon());
    }
    return {new TunnelWindowBox(toQString(title))};
}

Bool php_qt_tunnel_is_open(var box) {
    return windowBox(box)->isOpen();
}

void php_qt_tunnel_process_events(var box) {
    windowBox(box)->processEvents();
}

Array php_qt_tunnel_poll_event(var box) {
    return windowBox(box)->pollEvent();
}

void php_qt_tunnel_set_rules(var box, Array rules) {
    windowBox(box)->setRules(rules);
}

Bool php_qt_tunnel_start_process(var box, String id, String program, Array arguments) {
    return windowBox(box)->startProcess(toQString(id), toQString(program), arguments);
}

void php_qt_tunnel_stop_process(var box, String id) {
    windowBox(box)->stopProcess(toQString(id));
}

void php_qt_tunnel_append_log(var box, String id, String message) {
    windowBox(box)->appendLog(toQString(id), toQString(message));
}

void php_qt_tunnel_show_error(var box, String message) {
    windowBox(box)->showError(toQString(message));
}

void php_qt_tunnel_destroy(var box) {
    windowBox(box)->cleanup();
}
