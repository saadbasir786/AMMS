package com.amms.terminal

import android.os.Bundle
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity

class MainActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            textDirection = TextView.TEXT_DIRECTION_RTL
            layoutDirection = LinearLayout.LAYOUT_DIRECTION_RTL
            setPadding(48, 36, 48, 36)
        }
        val title = TextView(this).apply {
            text = "سیستم ترمینال مدیریت جلسات AMMS"
            textSize = 26f
        }
        val state = TextView(this).apply {
            text = "وضعیت: بسته  |  درایور آزمایشی FLOW-10A فعال است"
            textSize = 17f
        }
        val enrollment = Button(this).apply { text = "حالت ثبت اثر انگشت" }
        val meeting = Button(this).apply { text = "حالت جلسه و دریافت Candidate Set" }
        val close = Button(this).apply { text = "بستن جلسه و پاک‌سازی حافظه" }
        root.addView(title)
        root.addView(state)
        root.addView(enrollment)
        root.addView(meeting)
        root.addView(close)
        setContentView(root)
    }
}
