#include "phpx.h"

#include <QApplication>
#include <QAbstractItemView>
#include <QByteArray>
#include <QColor>
#include <QComboBox>
#include <QDialog>
#include <QDialogButtonBox>
#include <QEventLoop>
#include <QFormLayout>
#include <QFrame>
#include <QFont>
#include <QHash>
#include <QHeaderView>
#include <QHBoxLayout>
#include <QIcon>
#include <QLabel>
#include <QLineEdit>
#include <QList>
#include <QMainWindow>
#include <QMessageBox>
#include <QPainter>
#include <QPixmap>
#include <QPushButton>
#include <QSignalBlocker>
#include <QStyleFactory>
#include <QTableWidget>
#include <QTextEdit>
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
static char qt_program_name[] = "typephp-taskboard";
static char *qt_argv[] = {qt_program_name, nullptr};
static QApplication *qt_application = nullptr;

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

struct TaskView {
    QString id;
    QString title;
    QString description;
    QString status;
    QString statusLabel;
    QString priority;
    QString priorityLabel;
    QString createdAt;
};

TaskView fromPhpTask(const Array &row) {
    TaskView task;
    task.id = toQString(row.get("id"));
    task.title = toQString(row.get("title"));
    task.description = toQString(row.get("description"));
    task.status = toQString(row.get("status"));
    task.statusLabel = toQString(row.get("status_label"));
    task.priority = toQString(row.get("priority"));
    task.priorityLabel = toQString(row.get("priority_label"));
    task.createdAt = toQString(row.get("created_at"));
    return task;
}

QIcon applicationIcon() {
    QPixmap image(64, 64);
    image.fill(Qt::transparent);
    QPainter painter(&image);
    painter.setRenderHint(QPainter::Antialiasing);
    painter.setPen(Qt::NoPen);
    painter.setBrush(QColor("#5965E8"));
    painter.drawRoundedRect(2, 2, 60, 60, 15, 15);
    QFont font;
    font.setFamily("Arial");
    font.setPixelSize(38);
    font.setBold(true);
    painter.setFont(font);
    painter.setPen(Qt::white);
    painter.drawText(image.rect(), Qt::AlignCenter, "T");
    return QIcon(image);
}

class TaskDialog final : public QDialog {
  public:
    explicit TaskDialog(QWidget *parent, const TaskView &task) : QDialog(parent) {
        setWindowTitle(task.id.isEmpty() ? tr("新建任务") : tr("编辑任务"));
        setMinimumWidth(470);
        setObjectName("taskDialog");

        title_ = new QLineEdit(task.title);
        title_->setPlaceholderText(tr("例如：完成产品需求评审"));
        title_->setMaxLength(100);
        description_ = new QTextEdit(task.description);
        description_->setPlaceholderText(tr("补充任务背景、目标或验收标准"));
        description_->setMinimumHeight(130);
        status_ = new QComboBox();
        status_->addItem(tr("待处理"), "todo");
        status_->addItem(tr("进行中"), "doing");
        status_->addItem(tr("已完成"), "done");
        priority_ = new QComboBox();
        priority_->addItem(tr("普通"), "normal");
        priority_->addItem(tr("高优先级"), "high");
        priority_->addItem(tr("低优先级"), "low");
        if (!task.status.isEmpty()) {
            status_->setCurrentIndex(status_->findData(task.status));
        }
        if (!task.priority.isEmpty()) {
            priority_->setCurrentIndex(priority_->findData(task.priority));
        }

        auto *form = new QFormLayout();
        form->setSpacing(14);
        form->addRow(tr("任务标题"), title_);
        form->addRow(tr("详细说明"), description_);
        form->addRow(tr("当前状态"), status_);
        form->addRow(tr("优先级"), priority_);
        auto *buttons = new QDialogButtonBox(QDialogButtonBox::Save | QDialogButtonBox::Cancel);
        buttons->button(QDialogButtonBox::Save)->setText(tr("保存任务"));
        buttons->button(QDialogButtonBox::Cancel)->setText(tr("取消"));
        QObject::connect(buttons, &QDialogButtonBox::accepted, this, &QDialog::accept);
        QObject::connect(buttons, &QDialogButtonBox::rejected, this, &QDialog::reject);

        auto *layout = new QVBoxLayout(this);
        layout->setContentsMargins(24, 24, 24, 20);
        layout->addLayout(form);
        layout->addSpacing(12);
        layout->addWidget(buttons);
        title_->setFocus();
    }

    Array payload(const QString &id) const {
        Array data;
        data.set("id", toPhpString(id));
        data.set("title", toPhpString(title_->text()));
        data.set("description", toPhpString(description_->toPlainText()));
        data.set("status", toPhpString(status_->currentData().toString()));
        data.set("priority", toPhpString(priority_->currentData().toString()));
        return data;
    }

  private:
    QLineEdit *title_ = nullptr;
    QTextEdit *description_ = nullptr;
    QComboBox *status_ = nullptr;
    QComboBox *priority_ = nullptr;
};

class TaskWindowBox final : public Box {
  public:
    static QString tr(const char *text) { return QObject::tr(text); }

    explicit TaskWindowBox(const QString &title) {
        window_ = new QMainWindow();
        window_->setWindowTitle(title);
        window_->setWindowIcon(QApplication::windowIcon());
        window_->setMinimumSize(1060, 680);
        window_->resize(1280, 760);

        auto *root = new QWidget();
        root->setObjectName("root");
        auto *shell = new QHBoxLayout(root);
        shell->setContentsMargins(0, 0, 0, 0);
        shell->setSpacing(0);

        auto *sidebar = new QFrame();
        sidebar->setObjectName("sidebar");
        sidebar->setFixedWidth(218);
        auto *side = new QVBoxLayout(sidebar);
        side->setContentsMargins(20, 27, 18, 22);
        side->setSpacing(6);
        auto *brand = new QLabel(tr("◆  TYPEPHP"));
        brand->setObjectName("brand");
        side->addWidget(brand);
        auto *workspace = new QLabel(tr("PERSONAL WORKSPACE"));
        workspace->setObjectName("sideCaption");
        side->addSpacing(44);
        side->addWidget(workspace);
        side->addSpacing(12);
        addNav(side, tr("全部任务"), "all", true);
        addNav(side, tr("待处理"), "todo", false);
        addNav(side, tr("进行中"), "doing", false);
        addNav(side, tr("已完成"), "done", false);
        side->addStretch();
        auto *sideFooter = new QLabel(tr("TYPEPHP  ×  Qt Widgets\n本地桌面任务板"));
        sideFooter->setObjectName("sideFooter");
        side->addWidget(sideFooter);
        shell->addWidget(sidebar);

        auto *mainArea = new QWidget();
        mainArea->setObjectName("mainArea");
        auto *main = new QVBoxLayout(mainArea);
        main->setContentsMargins(32, 28, 32, 28);
        main->setSpacing(20);

        auto *header = new QHBoxLayout();
        auto *heading = new QVBoxLayout();
        heading->setSpacing(3);
        auto *eyebrow = new QLabel(tr("WORKSPACE / OVERVIEW"));
        eyebrow->setObjectName("eyebrow");
        auto *pageTitle = new QLabel(tr("任务总览"));
        pageTitle->setObjectName("pageTitle");
        auto *subtitle = new QLabel(tr("把重要的工作，稳稳地向前推进。"));
        subtitle->setObjectName("subtitle");
        heading->addWidget(eyebrow);
        heading->addWidget(pageTitle);
        heading->addWidget(subtitle);
        header->addLayout(heading);
        header->addStretch();
        auto *add = new QPushButton(tr("＋  新建任务"));
        add->setObjectName("primaryButton");
        add->setMinimumSize(126, 40);
        header->addWidget(add);
        main->addLayout(header);

        auto *stats = new QHBoxLayout();
        stats->setSpacing(14);
        totalValue_ = addStat(stats, tr("全部任务"), "0", "#5965E8");
        doingValue_ = addStat(stats, tr("进行中"), "0", "#E5A33D");
        doneValue_ = addStat(stats, tr("已完成"), "0", "#3BAF82");
        main->addLayout(stats);

        auto *body = new QHBoxLayout();
        body->setSpacing(16);
        auto *listCard = new QFrame();
        listCard->setObjectName("card");
        auto *listLayout = new QVBoxLayout(listCard);
        listLayout->setContentsMargins(18, 18, 18, 18);
        listLayout->setSpacing(14);
        auto *listHead = new QHBoxLayout();
        listTitle_ = new QLabel(tr("所有任务"));
        listTitle_->setObjectName("sectionTitle");
        listHead->addWidget(listTitle_);
        listHead->addStretch();
        visibleLabel_ = new QLabel(tr("0 项"));
        visibleLabel_->setObjectName("muted");
        listHead->addWidget(visibleLabel_);
        listLayout->addLayout(listHead);

        search_ = new QLineEdit();
        search_->setPlaceholderText(tr("搜索任务标题或说明…"));
        search_->setClearButtonEnabled(true);
        search_->setMinimumHeight(38);
        listLayout->addWidget(search_);

        table_ = new QTableWidget(0, 4);
        table_->setHorizontalHeaderLabels({tr("任务"), tr("优先级"), tr("状态"), tr("创建日期")});
        table_->setSelectionBehavior(QAbstractItemView::SelectRows);
        table_->setSelectionMode(QAbstractItemView::SingleSelection);
        table_->setEditTriggers(QAbstractItemView::NoEditTriggers);
        table_->setShowGrid(false);
        table_->setAlternatingRowColors(false);
        table_->setFocusPolicy(Qt::NoFocus);
        table_->verticalHeader()->hide();
        table_->verticalHeader()->setDefaultSectionSize(52);
        table_->horizontalHeader()->setSectionResizeMode(0, QHeaderView::Stretch);
        table_->horizontalHeader()->setSectionResizeMode(1, QHeaderView::Fixed);
        table_->horizontalHeader()->setSectionResizeMode(2, QHeaderView::Fixed);
        table_->horizontalHeader()->setSectionResizeMode(3, QHeaderView::Fixed);
        table_->setColumnWidth(1, 96);
        table_->setColumnWidth(2, 96);
        table_->setColumnWidth(3, 110);
        listLayout->addWidget(table_, 1);
        body->addWidget(listCard, 1);

        auto *detailCard = new QFrame();
        detailCard->setObjectName("card");
        detailCard->setFixedWidth(278);
        auto *detail = new QVBoxLayout(detailCard);
        detail->setContentsMargins(20, 19, 20, 20);
        detail->setSpacing(10);
        auto *detailHeading = new QLabel(tr("任务详情"));
        detailHeading->setObjectName("sectionTitle");
        detail->addWidget(detailHeading);
        detail->addSpacing(18);
        detailTitle_ = new QLabel(tr("选择一项任务"));
        detailTitle_->setObjectName("detailTitle");
        detailTitle_->setWordWrap(true);
        detail->addWidget(detailTitle_);
        detailMeta_ = new QLabel(tr("在左侧列表中查看任务详情"));
        detailMeta_->setObjectName("muted");
        detailMeta_->setWordWrap(true);
        detail->addWidget(detailMeta_);
        detail->addSpacing(12);
        auto *descriptionLabel = new QLabel(tr("任务说明"));
        descriptionLabel->setObjectName("eyebrow");
        detail->addWidget(descriptionLabel);
        detailDescription_ = new QLabel(tr("选择任务后显示说明。"));
        detailDescription_->setObjectName("description");
        detailDescription_->setWordWrap(true);
        detailDescription_->setAlignment(Qt::AlignTop);
        detail->addWidget(detailDescription_, 1);
        advanceButton_ = new QPushButton(tr("推进任务状态"));
        advanceButton_->setObjectName("secondaryButton");
        editButton_ = new QPushButton(tr("编辑任务"));
        editButton_->setObjectName("secondaryButton");
        deleteButton_ = new QPushButton(tr("删除任务"));
        deleteButton_->setObjectName("dangerButton");
        detail->addWidget(advanceButton_);
        detail->addWidget(editButton_);
        detail->addWidget(deleteButton_);
        body->addWidget(detailCard);
        main->addLayout(body, 1);
        shell->addWidget(mainArea, 1);
        window_->setCentralWidget(root);

        QObject::connect(add, &QPushButton::clicked, window_, [this]() { openEditor({}); });
        QObject::connect(search_, &QLineEdit::textChanged, window_, [this](const QString &text) {
            enqueue("search", {}, text);
        });
        QObject::connect(table_, &QTableWidget::itemSelectionChanged, window_, [this]() {
            showDetails();
            enqueue("select", selectedId());
        });
        QObject::connect(table_, &QTableWidget::cellDoubleClicked, window_, [this](int, int) {
            openEditor(selectedId());
        });
        QObject::connect(advanceButton_, &QPushButton::clicked, window_, [this]() {
            if (!selectedId().isEmpty()) enqueue("advance", selectedId());
        });
        QObject::connect(editButton_, &QPushButton::clicked, window_, [this]() { openEditor(selectedId()); });
        QObject::connect(deleteButton_, &QPushButton::clicked, window_, [this]() {
            const QString id = selectedId();
            if (!id.isEmpty() && QMessageBox::question(window_, tr("删除任务"), tr("确定删除这项任务吗？")) == QMessageBox::Yes) {
                enqueue("delete", id);
            }
        });
        QObject::connect(window_, &QObject::destroyed, [this]() { window_ = nullptr; });
        window_->show();
        showDetails();
    }

    bool isOpen() const { return window_ != nullptr && window_->isVisible(); }

    void processEvents() {
        if (!qt_application) return;
        QEventLoop loop;
        QTimer::singleShot(16, &loop, &QEventLoop::quit);
        loop.exec(QEventLoop::AllEvents);
    }

    Array pollEvent() {
        if (events_.empty()) return {};
        Array event = events_.front();
        events_.pop_front();
        return event;
    }

    void setView(const Array &rows, const Array &metrics, const QString &selected) {
        totalValue_->setText(QString::number(metrics.get("total").toInt()));
        doingValue_->setText(QString::number(metrics.get("doing").toInt()));
        doneValue_->setText(QString::number(metrics.get("done").toInt()));
        visibleLabel_->setText(tr("%1 项").arg(metrics.get("visible").toInt()));

        QSignalBlocker block(table_);
        tasks_.clear();
        table_->setRowCount(static_cast<int>(rows.count()));
        int selectedRow = -1;
        for (size_t i = 0; i < rows.count(); ++i) {
            const TaskView task = fromPhpTask(rows.get(i).toArray());
            tasks_.insert(task.id, task);
            const int row = static_cast<int>(i);
            auto *title = new QTableWidgetItem(task.title);
            title->setData(Qt::UserRole, task.id);
            title->setForeground(QColor("#20243A"));
            table_->setItem(row, 0, title);
            auto *priority = new QTableWidgetItem(task.priorityLabel);
            priority->setForeground(QColor(task.priority == "high" ? "#D36450" : "#7B8193"));
            table_->setItem(row, 1, priority);
            auto *status = new QTableWidgetItem(task.statusLabel);
            status->setForeground(QColor(task.status == "done" ? "#2A9D73" : task.status == "doing" ? "#D3902F" : "#71798D"));
            table_->setItem(row, 2, status);
            auto *date = new QTableWidgetItem(task.createdAt);
            date->setForeground(QColor("#969CAF"));
            table_->setItem(row, 3, date);
            if (task.id == selected) selectedRow = row;
        }
        if (selectedRow < 0 && rows.count() > 0) selectedRow = 0;
        if (selectedRow >= 0) table_->selectRow(selectedRow);
        showDetails();
    }

    void showError(const QString &message) {
        QMessageBox::warning(window_, tr("TypePHP Taskboard"), message);
    }

    bool snapshot(const QString &path) {
        qt_application->processEvents();
        return window_ && window_->grab().save(path, "PNG");
    }

    void cleanup() {
        if (window_) {
            delete window_;
            window_ = nullptr;
        }
    }

  private:
    void addNav(QVBoxLayout *layout, const QString &label, const QString &filter, bool checked) {
        auto *button = new QPushButton(label);
        button->setObjectName("navButton");
        button->setCheckable(true);
        button->setChecked(checked);
        button->setMinimumHeight(39);
        navButtons_.append(button);
        layout->addWidget(button);
        QObject::connect(button, &QPushButton::clicked, window_, [this, filter, button]() {
            for (QPushButton *item : navButtons_) item->setChecked(item == button);
            listTitle_->setText(button->text());
            enqueue("filter", {}, filter);
        });
    }

    QLabel *addStat(QHBoxLayout *layout, const QString &label, const QString &value, const QString &color) {
        auto *card = new QFrame();
        card->setObjectName("statCard");
        auto *content = new QVBoxLayout(card);
        content->setContentsMargins(20, 16, 20, 15);
        auto *caption = new QLabel(label);
        caption->setObjectName("statCaption");
        auto *number = new QLabel(value);
        number->setObjectName("statNumber");
        number->setStyleSheet("color: " + color + ";");
        content->addWidget(caption);
        content->addWidget(number);
        layout->addWidget(card, 1);
        return number;
    }

    QString selectedId() const {
        const int row = table_->currentRow();
        if (row < 0 || !table_->item(row, 0)) return {};
        return table_->item(row, 0)->data(Qt::UserRole).toString();
    }

    void showDetails() {
        const QString id = selectedId();
        const bool hasTask = !id.isEmpty() && tasks_.contains(id);
        advanceButton_->setEnabled(hasTask);
        editButton_->setEnabled(hasTask);
        deleteButton_->setEnabled(hasTask);
        if (!hasTask) {
            detailTitle_->setText(tr("选择一项任务"));
            detailMeta_->setText(tr("在左侧列表中查看任务详情"));
            detailDescription_->setText(tr("选择任务后显示说明。"));
            return;
        }
        const TaskView task = tasks_.value(id);
        detailTitle_->setText(task.title);
        detailMeta_->setText(task.statusLabel + "  ·  " + task.priorityLabel + "\n" + tr("创建于 ") + task.createdAt);
        detailDescription_->setText(task.description.isEmpty() ? tr("暂无详细说明") : task.description);
        advanceButton_->setText(task.status == "done" ? tr("重新开始") : task.status == "todo" ? tr("开始任务") : tr("标记为完成"));
    }

    void openEditor(const QString &id) {
        TaskDialog dialog(window_, tasks_.value(id));
        if (dialog.exec() == QDialog::Accepted) {
            enqueue("save", id, {}, dialog.payload(id));
        }
    }

    void enqueue(const QString &type, const QString &id = {}, const QString &value = {}, const Array &payload = {}) {
        Array event;
        event.set("type", toPhpString(type));
        if (!id.isEmpty()) event.set("id", toPhpString(id));
        if (!value.isEmpty()) event.set("value", toPhpString(value));
        if (payload.count() > 0) event.set("payload", payload);
        events_.push_back(event);
    }

    QMainWindow *window_ = nullptr;
    QTableWidget *table_ = nullptr;
    QLineEdit *search_ = nullptr;
    QLabel *totalValue_ = nullptr;
    QLabel *doingValue_ = nullptr;
    QLabel *doneValue_ = nullptr;
    QLabel *visibleLabel_ = nullptr;
    QLabel *listTitle_ = nullptr;
    QLabel *detailTitle_ = nullptr;
    QLabel *detailMeta_ = nullptr;
    QLabel *detailDescription_ = nullptr;
    QPushButton *advanceButton_ = nullptr;
    QPushButton *editButton_ = nullptr;
    QPushButton *deleteButton_ = nullptr;
    QList<QPushButton *> navButtons_;
    QHash<QString, TaskView> tasks_;
    std::deque<Array> events_;
};

TaskWindowBox *windowBox(var box) { return box.toBox<TaskWindowBox>(); }

}  // namespace

var php_qt_board_create(String title) {
    if (!qt_application) {
        qt_application = new QApplication(qt_argc, qt_argv);
        qt_application->setStyle(QStyleFactory::create("Fusion"));
        qt_application->setApplicationName("TypePHP Taskboard");
        qt_application->setWindowIcon(applicationIcon());
        QFont uiFont = qt_application->font();
#ifdef Q_OS_WIN
        uiFont.setFamily("Microsoft YaHei UI");
#endif
        uiFont.setPointSize(10);
        qt_application->setFont(uiFont);
        qt_application->setStyleSheet(R"QSS(
            QWidget#root, QWidget#mainArea { background: #F6F7FB; }
            QFrame#sidebar { background: #171A2B; border: 0; }
            QLabel#brand { color: #FFFFFF; font-size: 17px; font-weight: 800; letter-spacing: 1px; }
            QLabel#sideCaption { color: #858AA6; font-size: 10px; font-weight: 700; }
            QLabel#sideFooter { color: #858AA6; font-size: 11px; line-height: 1.5; }
            QPushButton#navButton { color: #B5BAD1; text-align: left; padding-left: 16px; border: 0; border-radius: 8px; background: transparent; font-size: 13px; }
            QPushButton#navButton:hover { background: #292D45; color: #FFFFFF; }
            QPushButton#navButton:checked { background: #353B61; color: #FFFFFF; font-weight: 700; }
            QLabel#eyebrow { color: #9298AC; font-size: 10px; font-weight: 700; letter-spacing: 1px; }
            QLabel#pageTitle { color: #1C2035; font-size: 27px; font-weight: 800; }
            QLabel#subtitle, QLabel#muted { color: #8990A3; font-size: 12px; }
            QLabel#sectionTitle { color: #252A3C; font-size: 15px; font-weight: 700; }
            QLabel#detailTitle { color: #20243A; font-size: 17px; font-weight: 700; }
            QLabel#description { color: #60677D; font-size: 12px; }
            QFrame#card, QFrame#statCard { background: #FFFFFF; border: 1px solid #E8EAF2; border-radius: 13px; }
            QLabel#statCaption { color: #9298A9; font-size: 11px; }
            QLabel#statNumber { font-size: 27px; font-weight: 800; }
            QPushButton#primaryButton { background: #5965E8; color: #FFFFFF; border: 0; border-radius: 8px; font-weight: 700; padding: 8px 14px; }
            QPushButton#primaryButton:hover { background: #4854D8; }
            QPushButton#secondaryButton { background: #F0F2FF; color: #4F5BDD; border: 0; border-radius: 7px; padding: 8px; font-weight: 600; }
            QPushButton#secondaryButton:hover { background: #E4E8FF; }
            QPushButton#dangerButton { background: transparent; color: #C46463; border: 1px solid #F0D8D8; border-radius: 7px; padding: 8px; }
            QPushButton#dangerButton:hover { background: #FFF2F2; }
            QPushButton:disabled { color: #B8BDCB; background: #F3F4F8; }
            QLineEdit, QTextEdit, QComboBox { background: #FFFFFF; border: 1px solid #E0E3ED; border-radius: 7px; padding: 7px 10px; color: #293047; }
            QLineEdit:focus, QTextEdit:focus, QComboBox:focus { border: 1px solid #6975EA; }
            QTableWidget { background: #FFFFFF; border: 0; color: #293047; selection-background-color: #F0F2FF; selection-color: #293047; }
            QTableWidget::item { border-bottom: 1px solid #F0F1F5; padding-left: 7px; }
            QHeaderView::section { background: #FAFAFD; color: #9298A9; font-size: 11px; font-weight: 700; border: 0; border-bottom: 1px solid #ECEEF4; padding: 9px 6px; }
            QDialog { background: #FFFFFF; }
        )QSS");
    }
    return {new TaskWindowBox(toQString(title))};
}

Bool php_qt_board_is_open(var box) { return windowBox(box)->isOpen(); }
void php_qt_board_process_events(var box) { windowBox(box)->processEvents(); }
Array php_qt_board_poll_event(var box) { return windowBox(box)->pollEvent(); }
void php_qt_board_set_view(var box, Array rows, Array metrics, String selected) {
    windowBox(box)->setView(rows, metrics, toQString(selected));
}
Bool php_qt_board_snapshot(var box, String path) { return windowBox(box)->snapshot(toQString(path)); }
void php_qt_board_show_error(var box, String message) { windowBox(box)->showError(toQString(message)); }
void php_qt_board_destroy(var box) { windowBox(box)->cleanup(); }
