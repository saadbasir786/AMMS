package af.gov.aop.amms.terminal

import af.gov.aop.amms.terminal.biometrics.DermalogF1Adapter
import af.gov.aop.amms.terminal.biometrics.IdentificationMatcher
import af.gov.aop.amms.terminal.biometrics.TerminalSessionController
import af.gov.aop.amms.terminal.net.IdentificationSessionStart
import af.gov.aop.amms.terminal.net.MeetingSummary
import af.gov.aop.amms.terminal.net.ParticipantItem
import af.gov.aop.amms.terminal.net.LaravelTerminalClient
import android.animation.ValueAnimator
import android.app.Activity
import android.app.Dialog
import android.content.Context
import android.content.res.ColorStateList
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.text.Editable
import android.text.InputFilter
import android.text.InputType
import android.text.TextWatcher
import android.util.Log
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.ViewConfiguration
import android.view.ViewGroup
import android.view.WindowManager
import android.view.animation.AccelerateDecelerateInterpolator
import android.view.animation.LinearInterpolator
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.Button
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.ScrollView
import android.widget.Spinner
import android.widget.TextView
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.NonCancellable
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.currentCoroutineContext
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.math.abs

/**
 * Ultra-Modern Next-Gen Kiosk Interface for DERMALOG SVT3000 SE.
 *
 * Design Aesthetics:
 * - Deep Obsidian Dark Theme (#030712 / #0F172A)
 * - Glassmorphism cards with glowing Emerald (#10B981) and Cyan (#06B6D4) accents
 * - Concentric animated biometric pulse rings
 * - Real-time participant search filter
 * - High-contrast RTL Persian typography
 */
class MainActivity : Activity() {

    private val activityScope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private lateinit var controller: TerminalSessionController
    private val terminalClient by lazy { LaravelTerminalClient(applicationContext) }

    // State
    private var activeMeetings: List<MeetingSummary> = emptyList()
    private var selectedMeeting: MeetingSummary? = null
    private var participants: List<ParticipantItem> = emptyList()
    private var filteredParticipants: List<ParticipantItem> = emptyList()
    private var selectedParticipant: ParticipantItem? = null
    private val eventType: String = "CHECK_IN"
    private var idMode: String = "quick_select" // quick_select, employee_id, qr_code
    private var searchQuery: String = ""
    private var isScanning = false
    private var captureJob: Job? = null
    private var heartbeatJob: Job? = null
    private val mainHandler = Handler(Looper.getMainLooper())
    /** Active identification session; held in RAM only, cleared after use. */
    private var activeIdentificationSession: IdentificationSessionStart? = null

    // UI elements
    private lateinit var meetingTitleText: TextView
    private lateinit var meetingMetaText: TextView
    private lateinit var meetingStatusBadge: TextView
    private lateinit var meetingSpinner: Spinner

    private lateinit var tabListBtn: TextView
    private lateinit var tabEmpIdBtn: TextView
    private lateinit var tabQrBtn: TextView

    private lateinit var searchInput: EditText
    private lateinit var participantsContainer: LinearLayout
    private lateinit var quickSelectPane: View
    private lateinit var empIdPane: LinearLayout
    private lateinit var qrPane: LinearLayout
    private lateinit var empIdInput: EditText
    private lateinit var selectedParticipantCard: LinearLayout
    private lateinit var selectedParticipantName: TextView
    private lateinit var selectedParticipantCode: TextView

    private lateinit var sensorOuterRing: View
    private lateinit var sensorInnerRing: View
    private lateinit var sensorStatusText: TextView
    private lateinit var sensorSubtextText: TextView
    private lateinit var scanActionButton: Button

    private lateinit var overlayContainer: FrameLayout
    private lateinit var rootContainer: FrameLayout
    private lateinit var pullToRefreshContainer: PullToRefreshContainer
    private var pairingOverlay: View? = null
    private var pairingStatusText: TextView? = null
    private var participantSheet: Dialog? = null
    private var participantSheetList: LinearLayout? = null
    private var participantSheetStatus: TextView? = null
    private var participantSheetCount: TextView? = null
    private var participantSheetMoreButton: Button? = null
    private var participantSheetSearch: EditText? = null
    private var participantSearchJob: Job? = null
    private var participantSearchQuery = ""
    private var participantCurrentPage = 0
    private var participantLastPage = 1
    private var participantRequestGeneration = 0

    private var outerRingAnimator: ValueAnimator? = null
    private var innerRingAnimator: ValueAnimator? = null

    private val dp get() = resources.displayMetrics.density

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        controller = TerminalSessionController(DermalogF1Adapter(this))

        // Fullscreen Kiosk Mode
        window.decorView.systemUiVisibility = (
            View.SYSTEM_UI_FLAG_FULLSCREEN or
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION or
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY or
            View.SYSTEM_UI_FLAG_LAYOUT_STABLE
        )

        rootContainer = FrameLayout(this).apply {
            setBackgroundColor(Color.parseColor("#030712"))
        }

        val scroll = ScrollView(this).apply {
            isVerticalScrollBarEnabled = false
            setBackgroundColor(Color.parseColor("#030712"))
        }
        pullToRefreshContainer = PullToRefreshContainer(this).apply {
            addView(scroll, 0, FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            ))
            setOnRefreshListener {
                refreshMeetings { pullToRefreshContainer.setRefreshing(false) }
            }
        }

        val mainContent = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(16), px(16), px(16), px(28))
        }

        val pageLayout = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(Color.parseColor("#030712"))
        }
        pageLayout.addView(buildAppBar(), LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            px(62)
        ))

        // 1. Active Meeting Hero Banner
        mainContent.addView(buildMeetingHeroCard())
        mainContent.addView(spacer(14))

        // 2. THE CENTERPIECE: Biometric Hero Scanner Panel
        mainContent.addView(buildBiometricHeroPanel())
        mainContent.addView(spacer(16))

        scroll.addView(mainContent, ViewGroup.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT
        ))

        pageLayout.addView(pullToRefreshContainer, LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            0,
            1f
        ))
        rootContainer.addView(pageLayout, FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ))

        // Result Modal Overlay Container
        overlayContainer = FrameLayout(this).apply {
            visibility = View.GONE
        }
        rootContainer.addView(overlayContainer, FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT
        ))

        setContentView(rootContainer)

        startBiometricPulseAnimation()
        if (terminalClient.isPaired) initializeTerminal() else showPairingScreen()
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. APP BAR
    // ─────────────────────────────────────────────────────────────────────────

    private fun buildAppBar(): View {
        val bar = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(px(12), px(7), px(12), px(7))
            setBackgroundColor(Color.parseColor("#0B132B"))
        }

        fun buttonParams() = LinearLayout.LayoutParams(
            0,
            ViewGroup.LayoutParams.MATCH_PARENT,
            1f
        ).apply { setMargins(px(4), 0, px(4), 0) }

        bar.addView(buildToolbarAction("کد اتصال", ToolbarIconType.KEYPAD, "#A7F3D0", "#064E3B") {
                captureJob?.cancel()
                terminalClient.clearPairing()
                showPairingScreen("کد ۴ رقمی جدید را از پنل وب وارد کنید.")
        }, buttonParams())

        bar.addView(buildToolbarAction("تازه‌سازی", ToolbarIconType.REFRESH, "#7DD3FC", "#082F49") {
            refreshMeetingsFromUser()
        }, buttonParams())

        bar.addView(buildToolbarAction("اشتراک‌کنندگان", ToolbarIconType.PEOPLE, "#C4B5FD", "#2E1065") {
            showParticipantsBottomSheet()
        }, buttonParams())

        return bar
    }

    private fun buildToolbarAction(
        label: String,
        iconType: ToolbarIconType,
        tintHex: String,
        backgroundHex: String,
        action: () -> Unit,
    ): View = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        gravity = Gravity.CENTER
        layoutDirection = View.LAYOUT_DIRECTION_RTL
        background = gradientDrawable(backgroundHex, null, 12, tintHex, 1.0f)
        isClickable = true
        isFocusable = true
        contentDescription = label
        setOnClickListener { action() }
        addView(ToolbarActionIcon(this@MainActivity, iconType, Color.parseColor(tintHex)), LinearLayout.LayoutParams(px(22), px(22)))
        addView(TextView(this@MainActivity).apply {
            text = label
            textSize = 9f
            typeface = Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER
            setTextColor(Color.parseColor(tintHex))
            maxLines = 1
        }, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            topMargin = px(2)
        })
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. ACTIVE MEETING HERO CARD
    // ─────────────────────────────────────────────────────────────────────────

    private fun buildMeetingHeroCard(): View {
        val container = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(20), px(18), px(20), px(18))
            background = gradientDrawable("#0F172A", "#0B132B", 20, "#10B981", 1.8f)
        }

        // Top Row: Live Status Pulse Dot & Label + Badge
        val headerRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
        }

        val liveDot = View(this).apply {
            background = circleDrawable("#10B981")
            layoutParams = LinearLayout.LayoutParams(px(10), px(10)).apply {
                setMargins(0, 0, px(8), 0)
            }
        }
        headerRow.addView(liveDot)

        val iconLabel = TextView(this).apply {
            text = "جلسه فعال سیستم"
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#34D399"))
        }
        headerRow.addView(iconLabel, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        meetingStatusBadge = TextView(this).apply {
            text = "در حال دریافت…"
            textSize = 11f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#38BDF8"))
            background = gradientDrawable("#083344", null, 10, "#38BDF8", 1.0f)
            setPadding(px(12), px(4), px(12), px(4))
        }
        headerRow.addView(meetingStatusBadge)

        container.addView(headerRow)
        container.addView(spacer(10))

        // Meeting Title
        meetingTitleText = TextView(this).apply {
            text = "در حال دریافت اطلاعات…"
            textSize = 17f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        }
        container.addView(meetingTitleText)

        // Meeting Metadata (Date & Location)
        meetingMetaText = TextView(this).apply {
            text = "لطفاً چند لحظه شکیبا باشید"
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#CBD5E1"))
            setPadding(0, px(6), 0, 0)
        }
        container.addView(meetingMetaText)

        // Multi-Meeting Glass Selector Bar (shows when multiple active meetings exist)
        val spinnerBox = LinearLayout(this).apply {
            id = View.generateViewId()
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            visibility = View.GONE
            setPadding(px(12), px(6), px(12), px(6))
            background = gradientDrawable("#030712", null, 12, "#38BDF8", 1.2f)
        }

        val spinnerLabel = TextView(this).apply {
            text = "🔄 تغییر جلسه:"
            textSize = 11f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#38BDF8"))
            setPadding(0, 0, px(8), 0)
        }
        spinnerBox.addView(spinnerLabel)

        meetingSpinner = Spinner(this).apply {
            setBackgroundColor(Color.TRANSPARENT)
        }
        spinnerBox.addView(meetingSpinner, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        container.addView(spinnerBox, LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT
        ).apply { setMargins(0, px(12), 0, 0) })

        return container
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. IDENTIFICATION HUB (Searchable List + Employee ID + QR)
    // ─────────────────────────────────────────────────────────────────────────

    private fun buildIdentificationHub(): View {
        val container = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            background = gradientDrawable("#0F172A", null, 16, "#1E293B", 1.0f)
            setPadding(px(14), px(12), px(14), px(12))
        }

        // Mode Navigation Tabs
        val tabs = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            background = gradientDrawable("#030712", null, 10)
            setPadding(px(3), px(3), px(3), px(3))
        }

        tabListBtn = buildTabPill("👥  شرکت‌کنندگان", true)
        tabEmpIdBtn = buildTabPill("🔢  کد کارمندی", false)
        tabQrBtn = buildTabPill("🎴  کارت شناسایی", false)

        tabListBtn.setOnClickListener { switchIdentificationTab("quick_select") }
        tabEmpIdBtn.setOnClickListener { switchIdentificationTab("employee_id") }
        tabQrBtn.setOnClickListener { switchIdentificationTab("qr_code") }

        tabs.addView(tabListBtn, LinearLayout.LayoutParams(0, px(36), 1.0f))
        tabs.addView(tabEmpIdBtn, LinearLayout.LayoutParams(0, px(36), 1.0f).apply { setMargins(px(3), 0, 0, 0) })
        tabs.addView(tabQrBtn, LinearLayout.LayoutParams(0, px(36), 1.0f).apply { setMargins(px(3), 0, 0, 0) })
        container.addView(tabs)
        container.addView(spacer(10))

        // Selected Participant Summary Card (Shows when someone is selected)
        selectedParticipantCard = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            visibility = View.GONE
            setPadding(px(12), px(8), px(12), px(8))
            background = gradientDrawable("#064E3B", null, 10, "#10B981", 1.0f)
        }

        val checkIcon = TextView(this).apply {
            text = "✓"
            textSize = 14f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#10B981"))
            background = circleDrawable("#022C22")
            gravity = Gravity.CENTER
            layoutParams = LinearLayout.LayoutParams(px(26), px(26))
        }
        selectedParticipantCard.addView(checkIcon)

        val selInfo = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(10), 0, 0, 0)
        }
        selectedParticipantName = TextView(this).apply {
            text = ""
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        }
        selectedParticipantCode = TextView(this).apply {
            text = ""
            textSize = 10f
            setTextColor(Color.parseColor("#6EE7B7"))
        }
        selInfo.addView(selectedParticipantName)
        selInfo.addView(selectedParticipantCode)
        selectedParticipantCard.addView(selInfo, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        val clearBtn = TextView(this).apply {
            text = "تغییر"
            textSize = 11f
            setTextColor(Color.parseColor("#93C5FD"))
            setPadding(px(8), px(4), px(8), px(4))
            setOnClickListener {
                selectedParticipant = null
                selectedParticipantCard.visibility = View.GONE
                renderParticipantsList()
                updateScanButtonState()
            }
        }
        selectedParticipantCard.addView(clearBtn)

        container.addView(selectedParticipantCard)
        container.addView(spacer(4))

        // PANE 1: QUICK SELECT LIST WITH REALTIME SEARCH
        val listPaneWrapper = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
        }

        searchInput = EditText(this).apply {
            hint = "🔍  جستجوی نام یا کد کارمندی…"
            textSize = 12f
            setTextColor(Color.WHITE)
            setHintTextColor(Color.parseColor("#64748B"))
            background = gradientDrawable("#030712", null, 10, "#1E293B", 1.0f)
            setPadding(px(12), px(10), px(12), px(10))
            addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                    searchQuery = s?.toString()?.trim() ?: ""
                    filterAndRenderParticipants()
                }
                override fun afterTextChanged(s: Editable?) {}
            })
        }
        listPaneWrapper.addView(searchInput)
        listPaneWrapper.addView(spacer(8))

        val scrollList = ScrollView(this).apply {
            isNestedScrollingEnabled = false
        }
        participantsContainer = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
        }
        scrollList.addView(participantsContainer)
        listPaneWrapper.addView(scrollList, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(240)))

        quickSelectPane = listPaneWrapper
        container.addView(quickSelectPane)

        // PANE 2: EMPLOYEE ID INPUT
        empIdPane = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            visibility = View.GONE
            setPadding(px(12), px(16), px(12), px(16))
            gravity = Gravity.CENTER
        }

        val idLabel = TextView(this).apply {
            text = "کد کارمندی خود را وارد کنید:"
            textSize = 12f
            setTextColor(Color.parseColor("#CBD5E1"))
        }
        empIdPane.addView(idLabel)

        empIdInput = EditText(this).apply {
            hint = "مثال: 1001"
            inputType = InputType.TYPE_CLASS_NUMBER
            textSize = 20f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            setHintTextColor(Color.parseColor("#334155"))
            gravity = Gravity.CENTER
            background = gradientDrawable("#030712", null, 12, "#334155", 1.5f)
            setPadding(px(16), px(14), px(16), px(14))
            addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                    updateScanButtonState()
                }
                override fun afterTextChanged(s: Editable?) {}
            })
        }
        empIdPane.addView(empIdInput, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT).apply {
            setMargins(0, px(10), 0, 0)
        })
        container.addView(empIdPane)

        // PANE 3: QR CARD READER
        qrPane = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            visibility = View.GONE
            gravity = Gravity.CENTER
            setPadding(px(16), px(24), px(16), px(24))
        }

        val qrIcon = TextView(this).apply {
            text = "🎴"
            textSize = 36f
            gravity = Gravity.CENTER
        }
        qrPane.addView(qrIcon)

        val qrText = TextView(this).apply {
            text = "کارت شناسایی را مقابل دستگاه بگیرید"
            textSize = 12f
            setTextColor(Color.parseColor("#06B6D4"))
            gravity = Gravity.CENTER
            setPadding(0, px(8), 0, 0)
        }
        qrPane.addView(qrText)
        container.addView(qrPane)

        return container
    }

    private fun buildTabPill(title: String, active: Boolean): TextView {
        return TextView(this).apply {
            text = title
            textSize = 11f
            typeface = Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER
            setTextColor(if (active) Color.WHITE else Color.parseColor("#64748B"))
            background = gradientDrawable(
                if (active) "#1E293B" else "#030712",
                null, 8,
                if (active) "#10B981" else null, 1.0f
            )
        }
    }

    private fun switchIdentificationTab(mode: String) {
        idMode = mode
        quickSelectPane.visibility = if (mode == "quick_select") View.VISIBLE else View.GONE
        empIdPane.visibility = if (mode == "employee_id") View.VISIBLE else View.GONE
        qrPane.visibility = if (mode == "qr_code") View.VISIBLE else View.GONE

        tabListBtn.background = gradientDrawable(if (mode == "quick_select") "#1E293B" else "#030712", null, 8, if (mode == "quick_select") "#10B981" else null, 1.0f)
        tabListBtn.setTextColor(if (mode == "quick_select") Color.WHITE else Color.parseColor("#64748B"))

        tabEmpIdBtn.background = gradientDrawable(if (mode == "employee_id") "#1E293B" else "#030712", null, 8, if (mode == "employee_id") "#10B981" else null, 1.0f)
        tabEmpIdBtn.setTextColor(if (mode == "employee_id") Color.WHITE else Color.parseColor("#64748B"))

        tabQrBtn.background = gradientDrawable(if (mode == "qr_code") "#1E293B" else "#030712", null, 8, if (mode == "qr_code") "#10B981" else null, 1.0f)
        tabQrBtn.setTextColor(if (mode == "qr_code") Color.WHITE else Color.parseColor("#64748B"))

        updateScanButtonState()
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. BIOMETRIC HERO PANEL WITH PULSE ANIMATIONS
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // 4. BIOMETRIC HERO PANEL WITH PULSE ANIMATIONS (CENTERPIECE)
    // ─────────────────────────────────────────────────────────────────────────

    private fun buildBiometricHeroPanel(): View {
        val container = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(px(24), px(28), px(24), px(28))
            background = gradientDrawable("#0B132B", "#0F172A", 20, "#10B981", 1.8f)
        }

        // Hero Scanner Pulse Frame (140dp x 140dp)
        val heroFrame = FrameLayout(this).apply {
            layoutParams = LinearLayout.LayoutParams(px(140), px(140)).apply {
                gravity = Gravity.CENTER_HORIZONTAL
                setMargins(0, 0, 0, px(16))
            }
        }

        sensorOuterRing = View(this).apply {
            background = ringDrawable("#10B981", 2.5f)
            layoutParams = FrameLayout.LayoutParams(px(140), px(140))
            alpha = 0.25f
        }

        sensorInnerRing = View(this).apply {
            background = ringDrawable("#06B6D4", 3.5f)
            layoutParams = FrameLayout.LayoutParams(px(112), px(112)).apply {
                gravity = Gravity.CENTER
            }
            alpha = 0.45f
        }

        val fingerprintIcon = TextView(this).apply {
            text = "☝"
            textSize = 52f
            gravity = Gravity.CENTER
            background = circleDrawable("#030712")
            layoutParams = FrameLayout.LayoutParams(px(88), px(88)).apply {
                gravity = Gravity.CENTER
            }
        }

        heroFrame.addView(sensorOuterRing)
        heroFrame.addView(sensorInnerRing)
        heroFrame.addView(fingerprintIcon)
        container.addView(heroFrame)

        sensorStatusText = TextView(this).apply {
            text = "آماده خواندن اثر انگشت"
            textSize = 15f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
        }
        container.addView(sensorStatusText)

        sensorSubtextText = TextView(this).apply {
            text = "با زدن دکمهٔ ثبت، حسگر روشن می‌شود؛ سپس انگشت را روی دستگاه بگذارید."
            textSize = 11f
            setTextColor(Color.parseColor("#94A3B8"))
            gravity = Gravity.CENTER
            setPadding(0, px(4), 0, px(18))
        }
        container.addView(sensorSubtextText)

        scanActionButton = Button(this).apply {
            text = "⚡  ثبت اثر انگشت یا حضور"
            textSize = 15f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            background = gradientDrawable("#10B981", "#059669", 16)
            setOnClickListener { executeBiometricVerification() }
        }
        container.addView(scanActionButton, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(56)))

        return container
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. COLLAPSIBLE PARTICIPANT DRAWER (Clean & Space-Efficient)
    // ─────────────────────────────────────────────────────────────────────────

    private fun buildCollapsibleParticipantDrawer(): View {
        val drawerCard = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            background = gradientDrawable("#0F172A", null, 16, "#1E293B", 1.0f)
            setPadding(px(14), px(12), px(14), px(12))
        }

        // Header Toggle Bar
        var isExpanded = false
        val headerBar = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(px(4), px(4), px(4), px(4))
        }

        val drawerIcon = TextView(this).apply {
            text = "👥"
            textSize = 14f
        }
        headerBar.addView(drawerIcon)

        val drawerTitle = TextView(this).apply {
            text = "  لیست شرکت‌کنندگان جلسه"
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        }
        headerBar.addView(drawerTitle, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        val arrowBadge = TextView(this).apply {
            text = "نمایش  ▼"
            textSize = 11f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#10B981"))
            background = gradientDrawable("#064E3B", null, 8, "#10B981", 1.0f)
            setPadding(px(10), px(4), px(10), px(4))
        }
        headerBar.addView(arrowBadge)
        drawerCard.addView(headerBar)

        // Expandable List Container
        val expandablePane = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            visibility = View.GONE
            setPadding(0, px(10), 0, 0)
        }

        searchInput = EditText(this).apply {
            hint = "🔍  جستجوی نام شرکت‌کننده…"
            textSize = 11f
            setTextColor(Color.WHITE)
            setHintTextColor(Color.parseColor("#64748B"))
            background = gradientDrawable("#030712", null, 8, "#1E293B", 1.0f)
            setPadding(px(10), px(8), px(10), px(8))
            addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                    searchQuery = s?.toString()?.trim() ?: ""
                    filterAndRenderParticipants()
                }
                override fun afterTextChanged(s: Editable?) {}
            })
        }
        expandablePane.addView(searchInput)
        expandablePane.addView(spacer(8))

        val scrollList = ScrollView(this).apply {
            isNestedScrollingEnabled = true
            setOnTouchListener { v, event ->
                v.parent.requestDisallowInterceptTouchEvent(true)
                false
            }
        }
        participantsContainer = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
        }
        scrollList.addView(participantsContainer)
        expandablePane.addView(scrollList, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(240)))

        drawerCard.addView(expandablePane)

        headerBar.setOnClickListener {
            isExpanded = !isExpanded
            expandablePane.visibility = if (isExpanded) View.VISIBLE else View.GONE
            arrowBadge.text = if (isExpanded) "بستن  ▲" else "نمایش  ▼"
        }

        return drawerCard
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ANIMATION & REAL-TIME LOGIC
    // ─────────────────────────────────────────────────────────────────────────

    private fun startBiometricPulseAnimation() {
        outerRingAnimator = ValueAnimator.ofFloat(0.15f, 0.7f, 0.15f).apply {
            duration = 2400
            repeatCount = ValueAnimator.INFINITE
            interpolator = AccelerateDecelerateInterpolator()
            addUpdateListener { sensorOuterRing.alpha = it.animatedValue as Float }
            start()
        }

        innerRingAnimator = ValueAnimator.ofFloat(0.3f, 0.9f, 0.3f).apply {
            duration = 1600
            repeatCount = ValueAnimator.INFINITE
            interpolator = LinearInterpolator()
            addUpdateListener { sensorInnerRing.alpha = it.animatedValue as Float }
            start()
        }
    }

    private fun showPairingScreen(
        message: String = "کد چهاررقمی اتصال صادرشده از پنل AMMS را وارد کنید. کد سه دقیقه اعتبار دارد."
    ) {
        pairingOverlay?.let { rootContainer.removeView(it) }

        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(px(28), px(28), px(28), px(28))
            setBackgroundColor(Color.parseColor("#030712"))
        }

        content.addView(TextView(this).apply {
            text = "اتصال ترمینال به ریاست"
            textSize = 25f
            typeface = Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER
            setTextColor(Color.WHITE)
        })
        content.addView(spacer(10))

        pairingStatusText = TextView(this).apply {
            text = message
            textSize = 15f
            gravity = Gravity.CENTER
            setTextColor(Color.parseColor("#94A3B8"))
        }
        content.addView(pairingStatusText)
        content.addView(spacer(24))

        // Pairing codes are issued for this registered terminal from the AMMS panel.
        val pinCard = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(px(24), px(24), px(24), px(24))
            background = gradientDrawable("#0F172A", "#1E293B", 18)
        }

        val baseUrlInput = EditText(this).apply {
            hint = "نشانی API، مانند https://amms.example"
            textSize = 13f
            gravity = Gravity.CENTER
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
            setTextColor(Color.WHITE)
            setHintTextColor(Color.parseColor("#475569"))
            background = gradientDrawable("#030712", "#0B1329", 12)
            setPadding(px(16), px(14), px(16), px(14))
        }
        // The API endpoint is a build-time deployment setting, not a field
        // that the operator should type on every terminal.
        baseUrlInput.setText(BuildConfig.AMMS_API_BASE_URL)
        baseUrlInput.visibility = View.GONE
        pinCard.addView(baseUrlInput, LinearLayout.LayoutParams(px(300), px(54)))
        pinCard.addView(spacer(10))

        val pairingCodeInput = EditText(this).apply {
            hint = "کد اتصال چهاررقمی"
            textSize = 24f
            typeface = Typeface.MONOSPACE
            gravity = Gravity.CENTER
            inputType = InputType.TYPE_CLASS_NUMBER or InputType.TYPE_NUMBER_VARIATION_PASSWORD
            filters = arrayOf(InputFilter.LengthFilter(4))
            setTextColor(Color.parseColor("#10B981"))
            setHintTextColor(Color.parseColor("#475569"))
            background = gradientDrawable("#030712", "#0B1329", 12)
            setPadding(px(16), px(14), px(16), px(14))
        }
        pinCard.addView(pairingCodeInput, LinearLayout.LayoutParams(px(300), px(60)))
        pinCard.addView(spacer(16))

        val submitBtn = Button(this).apply {
            text = "ذخیره و اتصال به AMMS"
            textSize = 16f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            background = gradientDrawable("#059669", "#047857", 12)
            setOnClickListener {
                val baseUrl = BuildConfig.AMMS_API_BASE_URL
                val pairingCode = pairingCodeInput.text.toString().trim()
                if (pairingCode.length != 4) {
                    pairingStatusText?.apply {
                        text = "کد اتصال باید چهار رقم باشد."
                        setTextColor(Color.parseColor("#F87171"))
                    }
                    return@setOnClickListener
                }
                submitPairingCode(baseUrl, pairingCode)
            }
        }
        pinCard.addView(submitBtn, LinearLayout.LayoutParams(px(280), px(54)))

        content.addView(pinCard, LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        ).apply {
            marginStart = px(32)
            marginEnd = px(32)
        })

        content.addView(spacer(20))

        content.addView(spacer(16))
        content.addView(TextView(this).apply {
            text = "شناسهٔ ترمینال در AMMS:\n${terminalClient.deviceCode}"
            textSize = 11f
            gravity = Gravity.CENTER
            setTextColor(Color.parseColor("#64748B"))
        })

        pairingOverlay = content
        rootContainer.addView(content, FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ))
    }

    private fun submitPairingCode(baseUrl: String, pairingCode: String) {
        pairingStatusText?.apply {
            text = "در حال بررسی اتصال به AMMS…"
            setTextColor(Color.parseColor("#38BDF8"))
        }
        activityScope.launch {
            terminalClient.pairWithCode(baseUrl, pairingCode).onSuccess { paired ->
                pairingStatusText?.apply {
                    text = "ترمینال ${paired.departmentName} متصل شد."
                    setTextColor(Color.parseColor("#10B981"))
                }
                mainHandler.postDelayed({
                    pairingOverlay?.let { rootContainer.removeView(it) }
                    pairingOverlay = null
                    initializeTerminal()
                }, 900L)
            }.onFailure { error ->
                Log.e("Terminal", "Laravel terminal pairing failed", error)
                pairingStatusText?.apply {
                    text = "کد اتصال معتبر نیست، منقضی شده یا ارتباط با AMMS برقرار نشد."
                    setTextColor(Color.parseColor("#F87171"))
                }
            }
        }
    }

    private fun initializeTerminal() {
        if (!terminalClient.isPaired) {
            showPairingScreen()
            return
        }
        startHeartbeatLoop()
        activityScope.launch {
            // Keep the integrated F1 power rail off during app startup. Creating
            // the DERMALOG SDK powers the sensor and can reset the USB host,
            // briefly disconnecting ADB. ensureOpen() initializes it only when
            // the operator starts an enrollment or attendance scan.
            // Fetch meetings scoped to the paired directorate.
            val meetRes = terminalClient.getActiveMeetings()
            meetRes.onSuccess { meetings ->
                activeMeetings = meetings
                if (meetings.isEmpty()) {
                    meetingTitleText.text = "هیچ جلسه فعالی یافت نشد"
                    meetingMetaText.text = "جلسه جدیدی در سیستم زمان‌بندی نشده است"
                    meetingStatusBadge.text = "بدون جلسه"
                    meetingStatusBadge.setTextColor(Color.parseColor("#94A3B8"))
                } else {
                    setupMeetingSpinner(meetings)
                }
            }.onFailure { err ->
                if (err.message?.contains("HTTP 401") == true ||
                    err.message?.contains("INVALID_DEVICE") == true ||
                    err.message?.contains("TERMINAL_UNAUTHORIZED") == true
                ) {
                    terminalClient.clearPairing()
                    showPairingScreen("اتصال قبلی معتبر نیست. کد جدید ریاست را وارد کنید.")
                    return@onFailure
                }
                meetingTitleText.text = "دریافت اطلاعات انجام نشد"
                meetingMetaText.text = "لطفاً اتصال شبکه دستگاه را بررسی نمایید"
            }
        }
    }

    private fun startHeartbeatLoop() {
        heartbeatJob?.cancel()
        heartbeatJob = activityScope.launch {
            while (terminalClient.isPaired) {
                terminalClient.heartbeat(BuildConfig.VERSION_NAME).onFailure { error ->
                    Log.w("Terminal", "AMMS terminal heartbeat failed", error)
                }
                delay(5 * 60 * 1000L)
            }
        }
    }

    private fun refreshMeetingsFromUser() {
        if (pullToRefreshContainer.isRefreshing) return
        pullToRefreshContainer.setRefreshing(true)
        refreshMeetings { pullToRefreshContainer.setRefreshing(false) }
    }

    private fun refreshMeetings(onComplete: (() -> Unit)? = null) {
        if (!terminalClient.isPaired) {
            onComplete?.invoke()
            return
        }
        activityScope.launch {
            try {
                val meetRes = terminalClient.getActiveMeetings()
                meetRes.onSuccess { meetings ->
                    activeMeetings = meetings
                    if (meetings.isEmpty()) {
                        selectedMeeting = null
                        meetingTitleText.text = "هیچ جلسه فعالی یافت نشد"
                        meetingMetaText.text = "جلسه جدیدی در سیستم زمان‌بندی نشده است"
                        meetingStatusBadge.text = "بدون جلسه"
                        meetingStatusBadge.setTextColor(Color.parseColor("#94A3B8"))
                        (meetingSpinner.parent as? View)?.visibility = View.GONE
                    } else {
                        setupMeetingSpinner(meetings)
                    }
                }.onFailure { err ->
                    if (err.message?.contains("HTTP 401") == true ||
                        err.message?.contains("INVALID_DEVICE") == true ||
                        err.message?.contains("TERMINAL_UNAUTHORIZED") == true
                    ) {
                        terminalClient.clearPairing()
                        showPairingScreen("اتصال قبلی معتبر نیست. کد جدید ریاست را وارد کنید.")
                        return@onFailure
                    }
                    meetingStatusBadge.text = "خطا در دریافت"
                    meetingStatusBadge.setTextColor(Color.parseColor("#F87171"))
                }
            } finally {
                onComplete?.invoke()
            }
        }
    }

    override fun onResume() {
        super.onResume()
        if (::meetingTitleText.isInitialized && terminalClient.isPaired && pairingOverlay == null) {
            refreshMeetings()
        }
    }

    private fun setupMeetingSpinner(meetings: List<MeetingSummary>) {
        val spinnerBox = meetingSpinner.parent as? View
        if (meetings.size <= 1) {
            spinnerBox?.visibility = View.GONE
            if (meetings.isNotEmpty()) {
                selectedMeeting = meetings.first()
                participants = emptyList()
                meetingTitleText.text = selectedMeeting!!.title
                meetingMetaText.text = "تاریخ: ${selectedMeeting!!.meetingDate}  |  محل: ${selectedMeeting!!.location ?: "سالن اصلی"}"
                meetingStatusBadge.text = meetingStatusLabel(selectedMeeting!!)
            }
            return
        }

        spinnerBox?.visibility = View.VISIBLE
        val titles = meetings.map { "${it.title} (${meetingStatusLabel(it)})" }

        val adapter = object : ArrayAdapter<String>(this, android.R.layout.simple_spinner_item, titles) {
            override fun getView(position: Int, convertView: View?, parent: ViewGroup): View {
                val v = super.getView(position, convertView, parent) as TextView
                v.setTextColor(Color.WHITE)
                v.textSize = 12f
                v.typeface = Typeface.DEFAULT_BOLD
                return v
            }

            override fun getDropDownView(position: Int, convertView: View?, parent: ViewGroup): View {
                val v = super.getDropDownView(position, convertView, parent) as TextView
                v.setTextColor(Color.WHITE)
                v.setBackgroundColor(Color.parseColor("#0F172A"))
                v.setPadding(px(14), px(12), px(14), px(12))
                v.textSize = 12f
                return v
            }
        }

        meetingSpinner.adapter = adapter
        meetingSpinner.onItemSelectedListener = object : AdapterView.OnItemSelectedListener {
            override fun onItemSelected(parent: AdapterView<*>?, view: View?, position: Int, id: Long) {
                selectedMeeting = meetings[position]
                participants = emptyList()
                meetingTitleText.text = selectedMeeting!!.title
                meetingMetaText.text = "تاریخ: ${selectedMeeting!!.meetingDate}  |  محل: ${selectedMeeting!!.location ?: "سالن اصلی"}"
                meetingStatusBadge.text = meetingStatusLabel(selectedMeeting!!)
            }
            override fun onNothingSelected(parent: AdapterView<*>?) {}
        }
    }

    private fun meetingStatusLabel(meeting: MeetingSummary): String {
        if (meeting.isStale) return "تخصیص نیازمند تازه‌سازی"
        return when (meeting.status) {
            "SCHEDULED" -> "برنامه‌ریزی‌شده"
            "IN_PROGRESS" -> "در حال برگزاری"
            "HELD" -> "پایان‌یافته"
            "COMPLETED" -> "تکمیل‌شده"
            "POSTPONED" -> "به‌تعویق‌افتاده"
            "CANCELLED" -> "لغوشده"
            else -> "وضعیت نامشخص"
        }
    }

    private fun identificationFailureMessage(error: Throwable?): Pair<String, String> {
        val code = error?.message?.substringBefore(':')?.trim()
        return when (code) {
            "MEETING_NOT_ATTENDANCE_ELIGIBLE", "MEETING_NOT_SCHEDULED" ->
                "وضعیت جلسه مناسب نیست" to "ثبت حضور فقط برای جلسهٔ برنامه‌ریزی‌شده یا در حال برگزاری امکان‌پذیر است."
            "TERMINAL_ASSIGNMENT_EXPIRED", "TERMINAL_ASSIGNMENT_WINDOW_NOT_OPEN" ->
                "بازهٔ مجاز ترمینال فعال نیست" to "زمان مجاز ثبت حضور هنوز آغاز نشده یا پایان یافته است. پنجرهٔ مجاز تخصیص ترمینال را بررسی کنید."
            "TERMINAL_ASSIGNMENT_STALE", "TERMINAL_SESSION_STALE" ->
                "اطلاعات جلسه تغییر کرده است" to "زمان‌بندی یا فهرست اشتراک‌کنندگان پس از تخصیص ترمینال تغییر کرده است. تخصیص را تازه‌سازی کنید."
            "TERMINAL_SESSION_ALREADY_ACTIVE" ->
                "نشست قبلی هنوز فعال است" to "برای این ترمینال یک نشست حاضری باز وجود دارد. نشست قبلی را ببندید یا وضعیت ترمینال را تازه‌سازی کنید."
            "TERMINAL_NOT_AVAILABLE" ->
                "ترمینال در دسترس نیست" to "وضعیت فعال‌بودن ترمینال را در سامانه بررسی کنید."
            else ->
                "ارتباط با سامانه برقرار نشد" to "لطفاً اتصال شبکه را بررسی کنید و دوباره تلاش کنید."
        }
    }

    private fun biometricEnrollmentFailureMessage(error: Throwable?): Pair<String, String> {
        val code = error?.message?.substringBefore(':')?.trim()
        return when (code) {
            "BIOMETRIC_ENROLLMENT_NOT_STARTED" ->
                "نشست جلسه فعال نیست" to "جلسه و تخصیص ترمینال را تازه‌سازی کنید و دوباره تلاش کنید."
            "BIOMETRIC_ENROLLMENT_EMPLOYEE_MISMATCH" ->
                "کارمند نشست با انتخاب شما یکسان نیست" to "نشست فعال برای کارمند دیگری است. مسئول ثبت باید نشست درست را برای اشتراک‌کنندهٔ انتخاب‌شده آغاز کند."
            "BIOMETRIC_ENROLLMENT_SESSION_EXPIRED" ->
                "نشست ثبت منقضی شده است" to "مسئول دارای صلاحیت باید نشست ثبت بایومتریک را دوباره آغاز کند."
            "BIOMETRIC_ENROLLMENT_AUTHORITY_REVOKED" ->
                "صلاحیت ثبت دیگر معتبر نیست" to "مسئول دارای صلاحیت باید نشست تازه‌ای آغاز کند."
            "BIOMETRIC_TEMPLATE_STALE" ->
                "این انگشت از قبل ثبت شده است" to "برای جایگزینی قالب موجود، از روند رسمی ثبت مجدد بایومتریک استفاده کنید."
            "BIOMETRIC_TEMPLATE_REPLACEMENT_NOT_ALLOWED" ->
                "جایگزینی اثر انگشت مجاز نیست" to "در نشست جلسه فقط می‌توان اثر انگشت ثبت‌نشده را اضافه کرد."
            "BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT" ->
                "کارمند در فهرست جلسه نیست" to "ثبت اثر انگشت فقط برای اشتراک‌کنندگان نسخهٔ رسمی همین جلسه ممکن است."
            "MEETING_BIOMETRIC_ENROLLMENT_COMPLETE" ->
                "ثبت اولیه کامل است" to "حداقل اثر انگشت‌های لازم برای این کارمند قبلاً ثبت شده است."
            "MEETING_NOT_ATTENDANCE_ELIGIBLE", "TERMINAL_SESSION_STALE", "TERMINAL_SESSION_INACTIVE" ->
                "نشست جلسه معتبر نیست" to "جلسه یا فهرست اشتراک‌کنندگان تغییر کرده است. جلسات را تازه‌سازی و دوباره تلاش کنید."
            "BIOMETRIC_ENROLLMENT_ALREADY_ACTIVE" ->
                "نشست ثبت دیگری فعال است" to "وضعیت نشست فعال کارمند یا ترمینال را در صفحهٔ بایومتریک بررسی و همان نشست را ادامه دهید."
            else ->
                "ثبت اثر انگشت انجام نشد" to "اتصال ترمینال و سامانه را بررسی کنید. اگر مشکل ادامه داشت، مسئول بایومتریک نشست ثبت و ترمینال انتخاب‌شده را بررسی کند."
        }
    }

    private fun showBiometricEnrollmentFailure(error: Throwable?, autoDismiss: Boolean = false) {
        val (title, message) = biometricEnrollmentFailureMessage(error)
        showResultModal("error", title, message, autoDismiss = autoDismiss)
    }

    private suspend fun prepareSmartEnrollment(
        probeBytes: ByteArray,
        sdkVersion: String,
        meeting: MeetingSummary,
    ) {
        val assignmentUuid = meeting.assignmentUuid
        if (assignmentUuid.isNullOrBlank()) {
            showResultModal(
                "error",
                "فهرست اشتراک‌کنندگان در دسترس نیست",
                "تخصیص ترمینال برای این جلسه را بررسی کنید.",
                autoDismiss = false,
            )
            return
        }

        val rosterResult = terminalClient.getMeetingParticipants(assignmentUuid)
        if (rosterResult.isFailure) {
            val error = rosterResult.exceptionOrNull() ?: IllegalStateException("PARTICIPANT_LIST_UNAVAILABLE")
            showResultModal("error", "دریافت فهرست انجام نشد", participantListErrorMessage(error), autoDismiss = false)
            return
        }

        participants = rosterResult.getOrThrow().participants
        showSmartEnrollmentModal(probeBytes, sdkVersion, meeting)
    }

    private fun loadParticipantsForMeeting(meetingId: String) {
        val meeting = activeMeetings.firstOrNull { it.id == meetingId }
            ?: selectedMeeting?.takeIf { it.id == meetingId }
            ?: return
        val assignmentUuid = meeting.assignmentUuid ?: return

        activityScope.launch {
            val res = terminalClient.getMeetingParticipants(assignmentUuid)
            res.onSuccess { list ->
                if (selectedMeeting?.assignmentUuid == assignmentUuid) {
                    participants = list.participants
                    participantSheetCount?.text = "${list.total} نفر"
                    if (participantSheet?.isShowing == true) {
                        participantCurrentPage = list.currentPage
                        participantLastPage = list.lastPage
                        renderParticipantSheetRows()
                    }
                }
            }.onFailure { err ->
                if (participantSheet?.isShowing == true) {
                    participantSheetStatus?.apply {
                        text = participantListErrorMessage(err)
                        setTextColor(Color.parseColor("#F87171"))
                        visibility = View.VISIBLE
                    }
                }
            }
        }
    }

    private fun showParticipantsBottomSheet() {
        val dialog = Dialog(this)
        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setPadding(px(20), px(12), px(20), px(16))
            background = gradientDrawable("#0F172A", null, 22, "#334155", 1.0f)
        }

        content.addView(View(this).apply {
            background = gradientDrawable("#475569", null, 4)
            layoutParams = LinearLayout.LayoutParams(px(42), px(4)).apply {
                gravity = Gravity.CENTER_HORIZONTAL
                bottomMargin = px(14)
            }
        })

        val header = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
        }
        header.addView(ToolbarActionIcon(this, ToolbarIconType.PEOPLE, Color.parseColor("#C4B5FD")), LinearLayout.LayoutParams(px(28), px(28)))
        val titleColumn = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(10), 0, px(8), 0)
        }
        titleColumn.addView(TextView(this).apply {
            text = "اشتراک‌کنندگان جلسه"
            textSize = 16f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        })
        titleColumn.addView(TextView(this).apply {
            text = selectedMeeting?.title.orEmpty()
            textSize = 10f
            setTextColor(Color.parseColor("#94A3B8"))
            maxLines = 1
        })
        header.addView(titleColumn, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        participantSheetCount = TextView(this).apply {
            text = "—"
            textSize = 10f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#C4B5FD"))
            background = gradientDrawable("#2E1065", null, 9)
            setPadding(px(10), px(6), px(10), px(6))
            contentDescription = "تعداد اشتراک‌کنندگان"
        }
        header.addView(participantSheetCount)
        header.addView(TextView(this).apply {
            text = "×"
            textSize = 24f
            gravity = Gravity.CENTER
            setTextColor(Color.parseColor("#CBD5E1"))
            background = gradientDrawable("#1E293B", null, 10)
            isClickable = true
            isFocusable = true
            contentDescription = "بستن فهرست"
            setOnClickListener { dialog.dismiss() }
        }, LinearLayout.LayoutParams(px(38), px(38)).apply { marginStart = px(10) })
        content.addView(header)
        content.addView(spacer(14))

        participantSheetSearch = EditText(this).apply {
            hint = "جستجو بر اساس نام یا شمارهٔ کارمندی"
            textSize = 12f
            isSingleLine = true
            setTextColor(Color.WHITE)
            setHintTextColor(Color.parseColor("#64748B"))
            background = gradientDrawable("#030712", null, 12, "#334155", 1.0f)
            setPadding(px(14), px(10), px(14), px(10))
            addTextChangedListener(object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) = Unit
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) = Unit
                override fun afterTextChanged(s: Editable?) {
                    val query = s?.toString()?.trim().orEmpty()
                    participantSearchJob?.cancel()
                    participantSearchJob = activityScope.launch {
                        delay(350)
                        participantSearchQuery = query
                        loadParticipantPage(1, append = false)
                    }
                }
            })
        }
        content.addView(participantSheetSearch, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(46)))
        content.addView(spacer(8))

        participantSheetStatus = TextView(this).apply {
            textSize = 11f
            gravity = Gravity.CENTER
            setTextColor(Color.parseColor("#94A3B8"))
            setPadding(px(8), px(8), px(8), px(8))
            visibility = View.GONE
        }
        content.addView(participantSheetStatus)

        val scroll = ScrollView(this).apply {
            isVerticalScrollBarEnabled = true
            isFillViewport = true
        }
        participantSheetList = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
        }
        scroll.addView(participantSheetList)
        content.addView(scroll, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f))

        participantSheetMoreButton = Button(this).apply {
            text = "نمایش نفرات بیشتر"
            textSize = 11f
            isAllCaps = false
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#DDD6FE"))
            background = gradientDrawable("#2E1065", null, 12, "#7C3AED", 1.0f)
            visibility = View.GONE
            setOnClickListener { loadParticipantPage(participantCurrentPage + 1, append = true) }
        }
        content.addView(participantSheetMoreButton, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(44)).apply {
            topMargin = px(10)
        })

        dialog.setContentView(content)
        dialog.setOnDismissListener {
            if (participantSheet === dialog) {
                participantSheet = null
                participantRequestGeneration++
            }
            participantSearchJob?.cancel()
        }
        dialog.show()
        dialog.window?.apply {
            setBackgroundDrawableResource(android.R.color.transparent)
            setLayout(ViewGroup.LayoutParams.MATCH_PARENT, (resources.displayMetrics.heightPixels * 0.78f).toInt())
            setGravity(Gravity.BOTTOM)
            val windowAttributes = attributes
            windowAttributes.dimAmount = 0.58f
            addFlags(WindowManager.LayoutParams.FLAG_DIM_BEHIND)
            attributes = windowAttributes
        }
        participantSheet = dialog
        participantSearchQuery = ""
        participants = emptyList()
        participantCurrentPage = 0
        participantLastPage = 1
        loadParticipantPage(1, append = false)
    }

    private fun loadParticipantPage(page: Int, append: Boolean) {
        val assignmentUuid = selectedMeeting?.assignmentUuid
        if (assignmentUuid.isNullOrBlank()) {
            participantSheetStatus?.apply {
                text = "برای این جلسه تخصیص فعال ترمینال پیدا نشد."
                setTextColor(Color.parseColor("#FBBF24"))
                visibility = View.VISIBLE
            }
            return
        }

        val requestGeneration = ++participantRequestGeneration
        participantSheetStatus?.apply {
            text = "در حال دریافت فهرست اشتراک‌کنندگان…"
            setTextColor(Color.parseColor("#94A3B8"))
            visibility = View.VISIBLE
        }
        participantSheetMoreButton?.isEnabled = false

        activityScope.launch {
            terminalClient.getMeetingParticipants(
                assignmentUuid = assignmentUuid,
                page = page,
                perPage = 50,
                search = participantSearchQuery,
            ).onSuccess { result ->
                if (requestGeneration != participantRequestGeneration) return@onSuccess
                if (selectedMeeting?.assignmentUuid != assignmentUuid) return@onSuccess
                participants = if (append) {
                    (participants + result.participants).distinctBy { it.employeeId }
                } else {
                    result.participants
                }
                participantCurrentPage = result.currentPage
                participantLastPage = result.lastPage
                participantSheetCount?.text = "${result.total} نفر"
                participantSheetStatus?.apply {
                    text = if (result.total == 0) "اشتراک‌کننده‌ای برای این جلسه ثبت نشده است." else ""
                    visibility = if (result.total == 0) View.VISIBLE else View.GONE
                }
                renderParticipantSheetRows()
            }.onFailure { error ->
                if (requestGeneration != participantRequestGeneration) return@onFailure
                participants = emptyList()
                participantSheetList?.removeAllViews()
                participantSheetStatus?.apply {
                    text = participantListErrorMessage(error)
                    setTextColor(Color.parseColor("#F87171"))
                    visibility = View.VISIBLE
                }
                participantSheetCount?.text = "—"
            }
            if (requestGeneration != participantRequestGeneration) return@launch
            participantSheetMoreButton?.apply {
                isEnabled = true
                visibility = if (participantCurrentPage in 1 until participantLastPage) View.VISIBLE else View.GONE
            }
        }
    }

    private fun renderParticipantSheetRows() {
        val container = participantSheetList ?: return
        container.removeAllViews()
        participants.forEachIndexed { index, participant ->
            if (index > 0) container.addView(spacer(5))
            container.addView(buildParticipantRowItem(participant))
        }
    }

    private fun participantListErrorMessage(error: Throwable): String = when (error.message?.substringBefore(':')) {
        "TERMINAL_ASSIGNMENT_WINDOW_NOT_OPEN" -> "فهرست پس از آغاز پنجرهٔ مجاز جلسه نمایش داده می‌شود."
        "TERMINAL_ASSIGNMENT_EXPIRED" -> "زمان مجاز نمایش فهرست این جلسه پایان یافته است."
        "TERMINAL_ASSIGNMENT_STALE" -> "تخصیص ترمینال با آخرین تغییرات جلسه هماهنگ نیست؛ آن را از پنل وب تازه کنید."
        "TERMINAL_ASSIGNMENT_UNAVAILABLE", "TERMINAL_ASSIGNMENT_NOT_FOUND" -> "تخصیص ترمینال برای این جلسه در دسترس نیست."
        else -> "دریافت فهرست انجام نشد. اتصال شبکه را بررسی و دوباره تلاش کنید."
    }

    private fun filterAndRenderParticipants() {
        filteredParticipants = if (searchQuery.isEmpty()) {
            participants
        } else {
            participants.filter {
                it.firstName.contains(searchQuery, ignoreCase = true) ||
                it.lastName.contains(searchQuery, ignoreCase = true) ||
                it.employeeNumber.contains(searchQuery, ignoreCase = true)
            }
        }
        renderParticipantsList()
    }

    private fun renderParticipantsList() {
        participantsContainer.removeAllViews()
        if (filteredParticipants.isEmpty()) {
            val emptyText = TextView(this).apply {
                text = if (searchQuery.isEmpty()) "هیچ کارمندی برای این جلسه تعریف نشده است" else "هیچ نتیجه‌ای برای \"$searchQuery\" یافت نشد"
                textSize = 11f
                setTextColor(Color.parseColor("#64748B"))
                gravity = Gravity.CENTER
                setPadding(0, px(24), 0, px(24))
            }
            participantsContainer.addView(emptyText)
            return
        }

        for (p in filteredParticipants) {
            participantsContainer.addView(buildParticipantRowItem(p))
            participantsContainer.addView(spacer(4))
        }
    }

    private fun buildParticipantRowItem(p: ParticipantItem): View {
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(px(12), px(10), px(12), px(10))
            background = gradientDrawable("#030712", null, 10, "#1E293B", 1.0f)
        }

        // Avatar Initial Circle
        val avatar = TextView(this).apply {
            text = p.firstName.take(1)
            textSize = 13f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
            background = circleDrawable(if (p.attended) "#064E3B" else "#1E293B")
            layoutParams = LinearLayout.LayoutParams(px(38), px(38))
        }
        row.addView(avatar)

        // Employee Name & Code Column
        val infoCol = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(10), 0, 0, 0)
        }

        val nameText = TextView(this).apply {
            text = "${p.firstName} ${p.lastName}"
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        }
        infoCol.addView(nameText)

        val codeText = TextView(this).apply {
            text = "کد: ${p.employeeNumber}${if (!p.jobTitle.isNullOrBlank()) "  •  ${p.jobTitle}" else ""}"
            textSize = 10f
            setTextColor(Color.parseColor("#94A3B8"))
        }
        infoCol.addView(codeText)
        row.addView(infoCol, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        // Status Badge (Checked-In vs Waiting)
        val statusBadge = TextView(this).apply {
            text = if (p.attended) "✓ حاضر" else "⏳ منتظر"
            textSize = 10f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(if (p.attended) Color.parseColor("#10B981") else Color.parseColor("#64748B"))
            background = gradientDrawable(
                if (p.attended) "#022C22" else "#0B132B",
                null, 6,
                if (p.attended) "#10B981" else "#1E293B", 1.0f
            )
            setPadding(px(8), px(4), px(8), px(4))
        }
        row.addView(statusBadge)

        return row
    }

    private fun updateScanButtonState() {
        val targetName = when (idMode) {
            "employee_id" -> empIdInput.text.toString().trim().let { if (it.isNotEmpty()) "کد $it" else null }
            else -> selectedParticipant?.let { "${it.firstName} ${it.lastName}" }
        }

        if (targetName != null) {
            sensorStatusText.text = "آماده خواندن اثر انگشت $targetName"
            sensorSubtextText.text = "با زدن دکمهٔ ثبت، حسگر روشن می‌شود؛ سپس انگشت را روی دستگاه بگذارید."
            scanActionButton.isEnabled = true
            scanActionButton.background = gradientDrawable("#10B981", null, 14)
        } else {
            sensorStatusText.text = "آماده ثبت اثر انگشت"
            sensorSubtextText.text = "ابتدا کارمند را از لیست بالا انتخاب فرمایید"
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BIOMETRIC WORKFLOW DISPATCHER
    // ─────────────────────────────────────────────────────────────────────────

    private fun executeBiometricVerification() {
        when (eventType) {
            "ENROLL" -> executeEnrollment()
            else -> executeIdentificationAttendance()
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENROLLMENT: Scan fingerprint → save template to central database
    // ─────────────────────────────────────────────────────────────────────────

    private fun executeEnrollment() {
        val participant = selectedParticipant
        if (participant == null) {
            showResultModal("error", "کارمند انتخاب نشده", "لطفاً ابتدا کارمند مورد نظر را از لیست انتخاب کنید.")
            return
        }
        val meeting = selectedMeeting
        if (meeting?.canRecordAttendance != true) {
            showBiometricEnrollmentFailure(IllegalStateException("MEETING_NOT_ATTENDANCE_ELIGIBLE"))
            return
        }

        isScanning = true
        scanActionButton.isEnabled = false
        scanActionButton.text = "در حال ثبت اثر انگشت…"
        sensorStatusText.text = "در حال دریافت اثر انگشت…"
        sensorSubtextText.text = "لطفاً انگشت شست راست را روی حسگر بگذارید"

        activityScope.launch {
            try {
            // Step 1: Open scanner
            val openRes = withContext(Dispatchers.IO) { controller.ensureOpen() }
            if (openRes.isFailure) {
                resetScanButtonState()
                showResultModal("error", "دستگاه آماده نیست", "اتصال دستگاه اثر انگشت را بررسی و دوباره تلاش کنید.")
                return@launch
            }

            // Step 2: Capture fingerprint template bytes for enrollment
            val captureRes = withContext(Dispatchers.IO) { controller.captureTemplateBytes() }
            if (captureRes.isFailure) {
                resetScanButtonState()
                showResultModal("error", "اثر انگشت خوانده نشد", "انگشت خود را درست روی دستگاه قرار دهید و دوباره تلاش کنید.")
                return@launch
            }

            val (templateBytes, sdkVersion) = captureRes.getOrThrow()

            // No more scanner work is needed for enrollment after capture/extraction.
            closeScannerSafely()

            // Step 3: Submit the template to the active Laravel enrollment session
            sensorStatusText.text = "در حال ثبت اثر انگشت…"
            val enrollRes = terminalClient.enrollMeetingParticipantTemplate(
                meetingId = meeting.id,
                employeeId = participant.employeeId,
                fingerPosition = "RIGHT_THUMB",
                templateBytes = templateBytes,
                sdkVersion = sdkVersion
            )

            isScanning = false
            resetScanButtonState()

            enrollRes.onSuccess {
                showResultModal(
                    type = "success",
                    title = "✓ اثر انگشت ثبت شد",
                    message = "این کارمند اکنون با اثر انگشت قابل شناسایی است.",
                    employeeName = "${participant.firstName} ${participant.lastName}"
                )
            }.onFailure { err ->
                Log.e("Terminal", "Fingerprint enrollment failed", err)
                val (title, message) = biometricEnrollmentFailureMessage(err)
                showResultModal("error", title, message)
            }
            } finally {
                closeScannerAfterWorkflow()
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ATTENDANCE: Smart Scan 1:N identification with Zero-Rescan On-the-Fly Enrollment
    // ─────────────────────────────────────────────────────────────────────────

    private fun executeIdentificationAttendance() {
        val meeting = selectedMeeting
        if (meeting == null) {
            showResultModal("error", "جلسه انتخاب نشده", "لطفاً ابتدا یک جلسه فعال از لیست انتخاب کنید.")
            return
        }
        if (meeting.isStale) {
            showResultModal("error", "تخصیص ترمینال تازه نیست", "زمان‌بندی یا فهرست اشتراک‌کنندگان جلسه تغییر کرده است. اطلاعات جلسات را تازه‌سازی و تخصیص ترمینال را بررسی کنید.")
            return
        }
        if (!meeting.canRecordAttendance) {
            showResultModal("error", "ثبت حضور در این وضعیت ممکن نیست", "وضعیت فعلی جلسه «${meetingStatusLabel(meeting)}» است؛ ثبت حضور فقط برای جلسهٔ برنامه‌ریزی‌شده یا در حال برگزاری امکان‌پذیر است.")
            return
        }

        isScanning = true
        scanActionButton.isEnabled = false
        scanActionButton.text = "در حال خواندن اثر انگشت…"
        sensorStatusText.text = "در حال خواندن اثر انگشت…"
        sensorSubtextText.text = "برای ثبت دقیق، انگشت شست راست را روی حسگر بگذارید"

        activityScope.launch {
            try {
            // Step 1: Open scanner
            val openRes = withContext(Dispatchers.IO) { controller.ensureOpen() }
            if (openRes.isFailure) {
                resetScanButtonState()
                showResultModal("error", "دستگاه آماده نیست", "اتصال دستگاه اثر انگشت را بررسی و دوباره تلاش کنید.")
                return@launch
            }

            // Step 2: Capture fingerprint template bytes
            val captureRes = withContext(Dispatchers.IO) { controller.captureTemplateBytes() }
            if (captureRes.isFailure) {
                resetScanButtonState()
                showResultModal("error", "اثر انگشت خوانده نشد", "انگشت خود را درست روی دستگاه قرار دهید و دوباره تلاش کنید.")
                return@launch
            }

            val (probeBytes, sdkVersion) = captureRes.getOrThrow()

            // Step 3: Fetch active session candidate templates from DB
            sensorStatusText.text = "در حال شناسایی اثر انگشت…"
            val sessionRes = terminalClient.startIdentificationSession(meeting.id)

            if (sessionRes.isFailure) {
                val error = sessionRes.exceptionOrNull()
                Log.e("Terminal", "Identification session could not be started", error)
                isScanning = false
                resetScanButtonState()
                closeScannerSafely()

                // With no enrolled biometric yet, the server returns NO_CANDIDATES.
                // The captured template is still valid, so let the operator choose
                // the meeting participant and enroll this same scan without asking
                // for the fingerprint a second time.
                if (error?.message?.contains("NO_CANDIDATES") == true) {
                    prepareSmartEnrollment(probeBytes, sdkVersion, meeting)
                    return@launch
                }

                val (title, message) = identificationFailureMessage(error)
                showResultModal("error", title, message)
                return@launch
            }

            val session = sessionRes.getOrThrow()

            // Step 4: Perform 1:N matching if candidate templates exist
            var matchedCandidateToken: String? = null
            var bestScore = 0.0
            var bestMargin = 0.0

            if (session.templates.isNotEmpty()) {
                activeIdentificationSession = session
                val probeTemplate = af.gov.aop.amms.terminal.biometrics.BiometricTemplate(
                    bytes = probeBytes,
                    format = 0,
                    sdkVersion = sdkVersion
                )

                val matcher = IdentificationMatcher(controller.getScanner())
                val matchResult = withContext(Dispatchers.IO) {
                    matcher.identify(probeTemplate, session)
                }

                matcher.wipeTemplates(session)
                activeIdentificationSession = null

                if (matchResult.identified && matchResult.candidateToken != null) {
                    matchedCandidateToken = matchResult.candidateToken
                    bestScore = matchResult.bestScore ?: 0.0
                    bestMargin = matchResult.margin ?: 0.0
                }
            }

            // Matching is the last step that needs the open scanner handle. Cut
            // power before attendance API round-trips and follow-up UI.
            closeScannerSafely()

            // Step 5A: MATCH FOUND -> Complete Session & Log Attendance
            if (matchedCandidateToken != null) {
                val completeRes = terminalClient.completeIdentificationSession(
                    sessionId = session.sessionId,
                    candidateToken = matchedCandidateToken,
                    verificationScore = bestScore,
                    margin = bestMargin
                )

                isScanning = false
                resetScanButtonState()

                completeRes.onSuccess { idResult ->
                    if (idResult.identified && idResult.employeeId != null) {

                        val matchedParticipant = participants.find { it.employeeId == idResult.employeeId }
                        val syncRes = terminalClient.submitAttendanceEvent(
                            meetingId = meeting.id,
                            employeeNumber = matchedParticipant?.employeeNumber ?: "",
                            eventType = eventType,
                            verificationResult = "MATCH",
                            verificationScore = bestScore,
                            participantId = matchedParticipant?.id,
                            employeeId = idResult.employeeId,
                            matchedTemplateUuid = idResult.matchedTemplateUuid,
                            fingerPosition = idResult.fingerPosition,
                            matcherVendor = idResult.matcherVendor,
                            matcherAlgorithmVersion = idResult.matcherAlgorithmVersion,
                            candidateSetChecksum = idResult.candidateSetChecksum,
                        )

                        syncRes.onSuccess { att ->
                            showResultModal(
                                type = if (att.duplicate) "already_checked_in" else "success",
                                title = if (att.duplicate) "حضور قبلاً ثبت شده" else if (eventType == "CHECK_IN") "✓ ورود ثبت شد" else "✓ خروج ثبت شد",
                                message = "اثر انگشت با موفقیت تأیید شد.",
                                employeeName = idResult.employeeName
                            )
                            loadParticipantsForMeeting(meeting.id)
                        }.onFailure { err ->
                            Log.e("Terminal", "Attendance registration failed", err)
                            if (err.message?.substringBefore(':')?.trim() == "ATTENDANCE_ALREADY_RECORDED") {
                                showResultModal(
                                    type = "already_checked_in",
                                    title = "حاضری قبلاً ثبت شده",
                                    message = "اثر انگشت تأیید شد؛ حاضری این اشتراک‌کننده قبلاً برای این جلسه ثبت شده و دوباره ثبت نشد.",
                                    employeeName = idResult.employeeName,
                                )
                                loadParticipantsForMeeting(meeting.id)
                            } else {
                                showResultModal("error", "حاضری ثبت نشد", "لطفاً اتصال شبکه را بررسی و دوباره تلاش کنید.")
                            }
                        }
                    } else {
                        showResultModal("error", "اثر انگشت تأیید نشد", "لطفاً انگشت خود را دوباره روی دستگاه قرار دهید.")
                    }
                }.onFailure { err ->
                    Log.e("Terminal", "Fingerprint identification failed", err)
                    showResultModal("error", "شناسایی انجام نشد", "لطفاً اتصال شبکه را بررسی و دوباره تلاش کنید.")
                }
                return@launch
            }

            // Step 5B: MATCH NOT FOUND (First time user or unrecognized)
            // Trigger Smart Enrollment Prompt holding captured template in RAM!
            isScanning = false
            resetScanButtonState()
            prepareSmartEnrollment(probeBytes, sdkVersion, meeting)
            } finally {
                closeScannerAfterWorkflow()
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SMART ZERO-RESCAN ENROLLMENT MODAL
    // ─────────────────────────────────────────────────────────────────────────

    private fun showSmartEnrollmentModal(
        probeBytes: ByteArray,
        sdkVersion: String,
        meeting: MeetingSummary,
    ) {
        overlayContainer.removeAllViews()
        overlayContainer.setBackgroundColor(Color.parseColor("#E6030712"))
        overlayContainer.visibility = View.VISIBLE

        val dialogBox = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER_HORIZONTAL
            setPadding(px(16), px(16), px(16), px(16))
            background = gradientDrawable("#0F172A", null, 18, "#1E293B", 1.5f)
            layoutParams = FrameLayout.LayoutParams(px(360), ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                gravity = Gravity.CENTER
            }
        }

        val headerRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
        }

        val iconView = TextView(this).apply {
            text = "☝"
            textSize = 22f
            gravity = Gravity.CENTER
            background = gradientDrawable("#022C22", null, 12, "#10B981", 1.0f)
        }
        headerRow.addView(iconView, LinearLayout.LayoutParams(px(46), px(46)))

        val headerText = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(12), 0, px(8), 0)
        }

        val titleView = TextView(this).apply {
            text = "انتخاب کارمند"
            textSize = 15f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        }
        headerText.addView(titleView)

        val subView = TextView(this).apply {
            text = "اثر انگشت شناسایی نشد؛ نام خود را انتخاب کنید."
            textSize = 10.5f
            setTextColor(Color.parseColor("#94A3B8"))
            setPadding(0, px(2), 0, 0)
        }
        headerText.addView(subView)
        headerRow.addView(headerText, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        val countBadge = TextView(this).apply {
            text = "${participants.size} نفر"
            textSize = 9.5f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#67E8F9"))
            gravity = Gravity.CENTER
            setPadding(px(8), px(4), px(8), px(4))
            background = gradientDrawable("#083344", null, 7, "#0E7490", 1.0f)
        }
        headerRow.addView(countBadge)
        dialogBox.addView(headerRow, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))
        dialogBox.addView(spacer(14))

        val modalSearchInput = EditText(this).apply {
            hint = "جستجوی نام یا کد کارمندی…"
            textSize = 11f
            setTextColor(Color.WHITE)
            setHintTextColor(Color.parseColor("#64748B"))
            isSingleLine = true
            gravity = Gravity.CENTER_VERTICAL
            setPadding(px(12), 0, px(12), 0)
            background = gradientDrawable("#030712", null, 10, "#1E293B", 1.0f)
        }
        dialogBox.addView(modalSearchInput, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(44)))
        dialogBox.addView(spacer(10))

        val scroll = ScrollView(this).apply {
            isNestedScrollingEnabled = false
            isVerticalScrollBarEnabled = false
        }
        val listContainer = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
        }

        fun renderEmployeeChoices(query: String = "") {
            listContainer.removeAllViews()
            val normalizedQuery = query.trim()
            val eligibleParticipants = participants
            val choices = if (normalizedQuery.isEmpty()) {
                eligibleParticipants
            } else {
                eligibleParticipants.filter { participant ->
                    participant.firstName.contains(normalizedQuery, ignoreCase = true) ||
                        participant.lastName.contains(normalizedQuery, ignoreCase = true) ||
                        participant.employeeNumber.contains(normalizedQuery, ignoreCase = true)
                }
            }

            if (choices.isEmpty()) {
                val emptyText = TextView(this).apply {
                    text = if (participants.isEmpty()) {
                        "هیچ کارمندی برای این جلسه وجود ندارد."
                    } else {
                        "کارمندی با این نام یا کد یافت نشد."
                    }
                    textSize = 11f
                    setTextColor(Color.parseColor("#64748B"))
                    gravity = Gravity.CENTER
                    setPadding(0, px(28), 0, px(28))
                }
                listContainer.addView(emptyText)
                return
            }

            choices.forEach { participant ->
                listContainer.addView(
                    buildEnrollmentParticipantRow(participant) {
                        overlayContainer.visibility = View.GONE
                        if (!meeting.canRecordAttendance) {
                            showBiometricEnrollmentFailure(IllegalStateException("MEETING_NOT_ATTENDANCE_ELIGIBLE"))
                            return@buildEnrollmentParticipantRow
                        }
                        if (meeting.canRecordAttendance) {
                            performInstantEnrollmentAndAttendance(participant, probeBytes, sdkVersion, meeting)
                        } else {
                            showBiometricEnrollmentFailure(IllegalStateException("MEETING_NOT_ATTENDANCE_ELIGIBLE"))
                        }
                    }
                )
                listContainer.addView(spacer(6))
            }
        }

        modalSearchInput.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) = Unit
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) = Unit
            override fun afterTextChanged(s: Editable?) = renderEmployeeChoices(s?.toString().orEmpty())
        })

        renderEmployeeChoices()
        scroll.addView(listContainer)
        dialogBox.addView(scroll, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(260)))
        dialogBox.addView(spacer(12))

        val cancelBtn = Button(this).apply {
            text = "بستن"
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#CBD5E1"))
            background = gradientDrawable("#1E293B", null, 10, "#334155", 1.0f)
            setOnClickListener { overlayContainer.visibility = View.GONE }
        }
        dialogBox.addView(cancelBtn, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(42)))

        overlayContainer.addView(dialogBox)
    }

    private fun buildEnrollmentParticipantRow(
        participant: ParticipantItem,
        onSelect: () -> Unit
    ): View {
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(px(12), px(10), px(12), px(10))
            background = gradientDrawable("#030712", null, 10, "#1E293B", 1.0f)
            isClickable = true
            isFocusable = true
            contentDescription = "انتخاب ${participant.firstName} ${participant.lastName}"
            setOnClickListener { onSelect() }
        }

        val avatar = TextView(this).apply {
            text = participant.firstName.take(1)
            textSize = 13f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
            background = circleDrawable(if (participant.attended) "#064E3B" else "#1E293B")
        }
        row.addView(avatar, LinearLayout.LayoutParams(px(38), px(38)))

        val infoColumn = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(px(10), 0, px(8), 0)
        }

        val nameText = TextView(this).apply {
            text = "${participant.firstName} ${participant.lastName}"
            textSize = 12f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
        }
        infoColumn.addView(nameText)

        val codeText = TextView(this).apply {
            text = "کد: ${participant.employeeNumber}${if (!participant.jobTitle.isNullOrBlank()) "  •  ${participant.jobTitle}" else ""}"
            textSize = 10f
            setTextColor(Color.parseColor("#94A3B8"))
        }
        infoColumn.addView(codeText)
        row.addView(infoColumn, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1.0f))

        val selectBadge = TextView(this).apply {
            text = "انتخاب"
            textSize = 10f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor("#34D399"))
            gravity = Gravity.CENTER
            setPadding(px(9), px(5), px(9), px(5))
            background = gradientDrawable("#022C22", null, 7, "#10B981", 1.0f)
        }
        row.addView(selectBadge)

        return row
    }

    private fun performInstantEnrollmentAndAttendance(
        participant: ParticipantItem,
        probeBytes: ByteArray,
        sdkVersion: String,
        meeting: MeetingSummary
    ) {
        sensorStatusText.text = "در حال ثبت اثر انگشت…"
        sensorSubtextText.text = "در حال بررسی نشست مجاز ثبت بایومتریک"
        isScanning = true
        scanActionButton.isEnabled = false

        activityScope.launch {
            try {
                // A roster selection is not a biometric match and never records
                // attendance. The active meeting session authorizes only this
                // participant's missing templates within the meeting snapshot.
                var result = terminalClient.enrollMeetingParticipantTemplate(
                    meetingId = meeting.id,
                    employeeId = participant.employeeId,
                    fingerPosition = "RIGHT_THUMB",
                    templateBytes = probeBytes,
                    sdkVersion = sdkVersion,
                )
                if (result.isFailure) {
                    val err = result.exceptionOrNull()
                    Log.e("Terminal", "Fingerprint enrollment failed", err)
                    val (title, message) = biometricEnrollmentFailureMessage(err)
                    showResultModal("error", title, message)
                    return@launch
                }

                var progress = result.getOrThrow()
                val fingerPositions = listOf(
                    "RIGHT_THUMB", "LEFT_THUMB", "RIGHT_INDEX", "LEFT_INDEX", "RIGHT_MIDDLE",
                    "LEFT_MIDDLE", "RIGHT_RING", "LEFT_RING", "RIGHT_LITTLE", "LEFT_LITTLE",
                )

                while (progress.activeFingerCount < progress.requiredFingerCount) {
                    val nextPosition = fingerPositions.firstOrNull { it !in progress.activeFingerPositions }
                    if (nextPosition == null) {
                        showResultModal(
                            "error",
                            "تعداد انگشت‌های لازم تکمیل نشد",
                            "جایگاه انگشت‌های باقی‌مانده مشخص نیست. یک انگشت دیگر را روی حسگر بگذارید و اسکن کنید.",
                        )
                        return@launch
                    }

                    val capture = captureAdditionalEnrollmentFinger(nextPosition) ?: return@launch
                    result = terminalClient.enrollMeetingParticipantTemplate(
                        meetingId = meeting.id,
                        employeeId = participant.employeeId,
                        fingerPosition = nextPosition,
                        templateBytes = capture.first,
                        sdkVersion = capture.second,
                    )
                    if (result.isFailure) {
                        val err = result.exceptionOrNull()
                        Log.e("Terminal", "Additional fingerprint enrollment failed", err)
                        val (title, message) = biometricEnrollmentFailureMessage(err)
                        showResultModal("error", title, message)
                        return@launch
                    }
                    progress = result.getOrThrow()
                }

                showResultModal(
                    type = "success",
                    title = "اثر انگشت ثبت شد",
                    message = "قالب‌های لازم ثبت شد. اشتراک‌کننده دوباره انگشت خود را اسکن کند تا حضور جلسه ثبت شود.",
                    employeeName = "${participant.firstName} ${participant.lastName}"
                )
                loadParticipantsForMeeting(meeting.id)
            } finally {
                closeScannerAfterWorkflow()
                isScanning = false
                resetScanButtonState()
            }
        }
    }

    private suspend fun captureAdditionalEnrollmentFinger(fingerPosition: String): Pair<ByteArray, String>? {
        sensorStatusText.text = "انگشت ${fingerPositionLabel(fingerPosition)} را اسکن کنید"
        sensorSubtextText.text = "انگشت ${fingerPositionLabel(fingerPosition)} را روی حسگر بگذارید و تا پایان اسکن ثابت نگه دارید"

        val openResult = withContext(Dispatchers.IO) { controller.ensureOpen() }
        if (openResult.isFailure) {
            closeScannerSafely()
            showResultModal("error", "حسگر آماده نیست", "اتصال سنسور اثر انگشت را بررسی و دوباره تلاش کنید.")
            return null
        }

        val captureResult = withContext(Dispatchers.IO) { controller.captureTemplateBytes() }
        closeScannerSafely()
        if (captureResult.isFailure) {
            showResultModal("error", "اسکن انگشت تکمیل نشد", "انگشت معرفی‌شده را درست روی حسگر بگذارید و دوباره تلاش کنید.")
            return null
        }
        return captureResult.getOrThrow()
    }

    private fun fingerPositionLabel(position: String): String = when (position) {
        "RIGHT_THUMB" -> "شست راست"
        "LEFT_THUMB" -> "شست چپ"
        "RIGHT_INDEX" -> "اشارهٔ راست"
        "LEFT_INDEX" -> "اشارهٔ چپ"
        "RIGHT_MIDDLE" -> "میانی راست"
        "LEFT_MIDDLE" -> "میانی چپ"
        "RIGHT_RING" -> "حلقهٔ راست"
        "LEFT_RING" -> "حلقهٔ چپ"
        "RIGHT_LITTLE" -> "کوچک راست"
        "LEFT_LITTLE" -> "کوچک چپ"
        else -> "موردنظر"
    }

    private fun resetScanButtonState() {
        scanActionButton.isEnabled = true
        scanActionButton.text = "⚡  ثبت اثر انگشت یا حضور"
        updateScanButtonState()
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. RESULT MODAL OVERLAY
    // ─────────────────────────────────────────────────────────────────────────

    private fun showResultModal(
        type: String,
        title: String,
        message: String,
        employeeName: String? = null,
        autoDismiss: Boolean = true,
    ) {
        overlayContainer.removeAllViews()
        overlayContainer.setBackgroundColor(Color.parseColor("#E6030712"))
        overlayContainer.visibility = View.VISIBLE

        val accentColor = when (type) {
            "success" -> "#10B981"
            "already_checked_in" -> "#F59E0B"
            "low_score" -> "#F59E0B"
            else -> "#EF4444"
        }

        val iconSymbol = when (type) {
            "success" -> "✓"
            "already_checked_in" -> "⚠️"
            "low_score" -> "⚠️"
            else -> "✕"
        }

        val dialogBox = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setPadding(px(24), px(28), px(24), px(24))
            background = gradientDrawable("#0F172A", null, 20, accentColor, 1.5f)
            layoutParams = FrameLayout.LayoutParams(px(320), ViewGroup.LayoutParams.WRAP_CONTENT).apply {
                gravity = Gravity.CENTER
            }
        }

        // Icon Header Circle
        val iconView = TextView(this).apply {
            text = iconSymbol
            textSize = 36f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.parseColor(accentColor))
            gravity = Gravity.CENTER
            background = circleDrawable("#0B132B")
            layoutParams = LinearLayout.LayoutParams(px(72), px(72)).apply {
                gravity = Gravity.CENTER_HORIZONTAL
                setMargins(0, 0, 0, px(14))
            }
        }
        dialogBox.addView(iconView)

        val titleView = TextView(this).apply {
            text = title
            textSize = 15f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
        }
        dialogBox.addView(titleView)
        dialogBox.addView(spacer(6))

        val msgView = TextView(this).apply {
            text = message
            textSize = 11f
            setTextColor(Color.parseColor("#CBD5E1"))
            gravity = Gravity.CENTER
        }
        dialogBox.addView(msgView)

        if (employeeName != null) {
            dialogBox.addView(spacer(10))
            val empBadge = TextView(this).apply {
                text = "👤  $employeeName"
                textSize = 12f
                typeface = Typeface.DEFAULT_BOLD
                setTextColor(Color.parseColor("#10B981"))
                background = gradientDrawable("#022C22", null, 8, "#10B981", 1.0f)
                setPadding(px(12), px(6), px(12), px(6))
                gravity = Gravity.CENTER
            }
            dialogBox.addView(empBadge)
        }

        dialogBox.addView(spacer(20))

        val closeBtn = Button(this).apply {
            text = "تایید و بستن"
            textSize = 13f
            typeface = Typeface.DEFAULT_BOLD
            setTextColor(Color.WHITE)
            background = gradientDrawable(accentColor, null, 12)
            setOnClickListener { overlayContainer.visibility = View.GONE }
        }
        dialogBox.addView(closeBtn, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(46)))

        overlayContainer.addView(dialogBox)

        if (autoDismiss) {
            // Auto-dismiss brief scan results; keep operational guidance visible until acknowledged.
            mainHandler.postDelayed(Runnable { overlayContainer.visibility = View.GONE }, 4500L)
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GRAPHICS DRAWABLE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private fun gradientDrawable(
        bgColor: String,
        gradientEndColor: String? = null,
        cornerRadiusDp: Int = 12,
        strokeColor: String? = null,
        strokeWidthDp: Float = 1.0f
    ): GradientDrawable {
        return GradientDrawable().apply {
            shape = GradientDrawable.RECTANGLE
            if (gradientEndColor != null) {
                orientation = GradientDrawable.Orientation.TOP_BOTTOM
                colors = intArrayOf(Color.parseColor(bgColor), Color.parseColor(gradientEndColor))
            } else {
                setColor(Color.parseColor(bgColor))
            }
            cornerRadius = cornerRadiusDp * dp
            if (strokeColor != null) {
                setStroke((strokeWidthDp * dp).toInt(), Color.parseColor(strokeColor))
            }
        }
    }

    private fun circleDrawable(colorHex: String): GradientDrawable {
        return GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(Color.parseColor(colorHex))
        }
    }

    private fun ringDrawable(colorHex: String, strokeWidthDp: Float = 2.0f): GradientDrawable {
        return GradientDrawable().apply {
            shape = GradientDrawable.OVAL
            setColor(Color.TRANSPARENT)
            setStroke((strokeWidthDp * dp).toInt(), Color.parseColor(colorHex))
        }
    }

    private fun spacer(heightDp: Int): View {
        return View(this).apply {
            layoutParams = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, px(heightDp))
        }
    }

    private fun px(dp: Int): Int = (dp * this.dp + 0.5f).toInt()

    private suspend fun closeScannerSafely() {
        withContext(NonCancellable + Dispatchers.IO) {
            controller.close().onFailure { error ->
                Log.e("Terminal", "Unable to close scanner or switch off fingerprint power", error)
            }
        }
    }

    private suspend fun closeScannerAfterWorkflow() {
        val workflowWasCancelled = !currentCoroutineContext().isActive
        withContext(NonCancellable + Dispatchers.IO) {
            if (workflowWasCancelled) {
                controller.cancelCapture().onFailure { error ->
                    Log.w("Terminal", "Scanner capture cancellation warning: ${error.message}")
                }
            }
            controller.close().onFailure { error ->
                Log.e("Terminal", "Unable to close scanner or switch off fingerprint power", error)
            }
        }
    }

    override fun onDestroy() {
        outerRingAnimator?.cancel()
        innerRingAnimator?.cancel()
        heartbeatJob?.cancel()
        captureJob?.cancel()
        activityScope.cancel()
        super.onDestroy()
    }
}

private enum class ToolbarIconType {
    KEYPAD,
    REFRESH,
    PEOPLE,
}

private class ToolbarActionIcon(
    context: Context,
    private val iconType: ToolbarIconType,
    tint: Int,
) : View(context) {
    private val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = tint
        style = Paint.Style.STROKE
        strokeWidth = 1.8f
        strokeCap = Paint.Cap.ROUND
        strokeJoin = Paint.Join.ROUND
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)
        val scale = minOf(width, height) / 24f
        if (scale <= 0f) return

        val iconSize = 24f
        val scaledIconSize = iconSize * scale
        val save = canvas.save()
        canvas.translate((width - scaledIconSize) / 2f, (height - scaledIconSize) / 2f)
        canvas.scale(scale, scale)

        when (iconType) {
            ToolbarIconType.KEYPAD -> {
                canvas.drawRoundRect(RectF(5f, 2f, 19f, 22f), 2.5f, 2.5f, paint)
                canvas.drawLine(7.5f, 6.5f, 16.5f, 6.5f, paint)
                paint.style = Paint.Style.FILL
                listOf(9f, 12f, 15f).forEach { x ->
                    listOf(10f, 13.5f, 17f).forEach { y -> canvas.drawCircle(x, y, 0.8f, paint) }
                }
                paint.style = Paint.Style.STROKE
            }
            ToolbarIconType.REFRESH -> {
                canvas.drawArc(RectF(4f, 4f, 20f, 20f), 48f, 290f, false, paint)
                val arrow = Path().apply {
                    moveTo(17.2f, 3.7f)
                    lineTo(20.4f, 4.2f)
                    lineTo(19.8f, 7.3f)
                }
                canvas.drawPath(arrow, paint)
            }
            ToolbarIconType.PEOPLE -> {
                canvas.drawCircle(9f, 8f, 3f, paint)
                canvas.drawArc(RectF(3.5f, 12f, 14.5f, 22f), 180f, 180f, false, paint)
                canvas.drawCircle(17f, 9f, 2.4f, paint)
                canvas.drawArc(RectF(13f, 13f, 22f, 21f), 190f, 160f, false, paint)
            }
        }

        canvas.restoreToCount(save)
    }
}

private class PullToRefreshContainer(context: Context) : FrameLayout(context) {
    private val density = context.resources.displayMetrics.density
    private val touchSlop = ViewConfiguration.get(context).scaledTouchSlop
    private val triggerDistance = 88f * density
    private val indicatorSize = (32f * density + 0.5f).toInt()
    private var touchStartX = 0f
    private var touchStartY = 0f
    private var isDragging = false
    private var refreshListener: (() -> Unit)? = null

    var isRefreshing: Boolean = false
        private set

    private val indicator = ProgressBar(context).apply {
        isIndeterminate = true
        indeterminateTintList = ColorStateList.valueOf(Color.parseColor("#34D399"))
        visibility = View.INVISIBLE
        elevation = 4f * density
    }

    init {
        clipToPadding = false
        addView(indicator, LayoutParams(indicatorSize, indicatorSize, Gravity.TOP or Gravity.CENTER_HORIZONTAL).apply {
            topMargin = (8f * density + 0.5f).toInt()
        })
    }

    fun setOnRefreshListener(listener: () -> Unit) {
        refreshListener = listener
    }

    fun setRefreshing(refreshing: Boolean) {
        isRefreshing = refreshing
        isDragging = false
        indicator.animate().cancel()
        if (refreshing) {
            indicator.visibility = View.VISIBLE
            indicator.translationY = 16f * density
        } else {
            hideIndicator()
        }
    }

    override fun onInterceptTouchEvent(event: MotionEvent): Boolean {
        if (isRefreshing) return false

        when (event.actionMasked) {
            MotionEvent.ACTION_DOWN -> {
                touchStartX = event.x
                touchStartY = event.y
                isDragging = false
            }
            MotionEvent.ACTION_MOVE -> {
                val pullDistance = event.y - touchStartY
                val horizontalDistance = abs(event.x - touchStartX)
                if (pullDistance > touchSlop && pullDistance > horizontalDistance && !canContentScrollUp()) {
                    isDragging = true
                    parent?.requestDisallowInterceptTouchEvent(true)
                    return true
                }
            }
        }
        return isDragging
    }

    override fun onTouchEvent(event: MotionEvent): Boolean {
        if (isRefreshing) return true

        when (event.actionMasked) {
            MotionEvent.ACTION_MOVE -> {
                val pullDistance = (event.y - touchStartY).coerceAtLeast(0f)
                if (isDragging) updateIndicator(pullDistance)
                return isDragging
            }
            MotionEvent.ACTION_UP -> {
                val pullDistance = (event.y - touchStartY).coerceAtLeast(0f)
                val shouldRefresh = isDragging && pullDistance >= triggerDistance
                isDragging = false
                if (shouldRefresh) {
                    setRefreshing(true)
                    refreshListener?.invoke()
                } else {
                    hideIndicator()
                }
                return true
            }
            MotionEvent.ACTION_CANCEL -> {
                isDragging = false
                hideIndicator()
                return true
            }
        }
        return true
    }

    private fun canContentScrollUp(): Boolean = childCount > 1 && getChildAt(0).canScrollVertically(-1)

    private fun updateIndicator(pullDistance: Float) {
        indicator.visibility = View.VISIBLE
        val visibleOffset = (pullDistance * 0.5f).coerceAtMost(indicatorSize + 20f * density)
        indicator.translationY = -indicatorSize + visibleOffset
    }

    private fun hideIndicator() {
        indicator.visibility = View.INVISIBLE
        indicator.translationY = -indicatorSize.toFloat()
    }
}
