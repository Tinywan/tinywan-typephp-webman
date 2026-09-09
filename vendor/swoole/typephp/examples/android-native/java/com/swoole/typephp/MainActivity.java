package com.swoole.typephp;

import android.app.Activity;
import android.content.res.ColorStateList;
import android.graphics.Color;
import android.graphics.Typeface;
import android.os.Bundle;
import android.util.SparseArray;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.TextView;

public final class MainActivity extends Activity {
    static {
        System.loadLibrary("typephp_android_hello");
    }

    private native void nativeApplicationDidLaunch();
    private native void nativeControlActivated(int controlId);

    private final SparseArray<View> controls = new SparseArray<>();
    private int nextControlId = 1;
    private LogicalLayout content;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        nativeApplicationDidLaunch();
    }

    private int storeControl(View control, int x, int y, int width, int height) {
        int controlId = nextControlId++;
        control.setId(controlId);
        controls.put(controlId, control);
        content.addView(control, content.new LayoutParams(x, y, width, height));
        return controlId;
    }

    private void uiCreateWindow(String title, int width, int height) {
        setTitle(title);
        content = new LogicalLayout(width, height);
        content.setBackgroundColor(Color.rgb(247, 249, 252));
        setContentView(content);
    }

    private int uiAddImage(String resourceName, int x, int y, int width, int height) {
        ImageView image = new ImageView(this);
        int resourceId = getResources().getIdentifier(resourceName, "drawable", getPackageName());
        if (resourceId != 0) {
            image.setImageResource(resourceId);
        }
        image.setScaleType(ImageView.ScaleType.CENTER_INSIDE);
        return storeControl(image, x, y, width, height);
    }

    private int uiAddLabel(
            String text,
            int x,
            int y,
            int width,
            int height,
            int fontSize,
            boolean bold) {
        TextView label = new TextView(this);
        label.setText(text);
        label.setTextSize(fontSize);
        label.setTextColor(bold ? Color.rgb(24, 36, 54) : Color.rgb(90, 103, 122));
        label.setTypeface(Typeface.DEFAULT, bold ? Typeface.BOLD : Typeface.NORMAL);
        label.setGravity(Gravity.CENTER);
        return storeControl(label, x, y, width, height);
    }

    private int uiAddTextInput(String hint, int x, int y, int width, int height) {
        EditText input = new EditText(this);
        input.setHint(hint);
        input.setSingleLine(true);
        input.setTextSize(18);
        input.setPadding(dp(12), 0, dp(12), 0);
        return storeControl(input, x, y, width, height);
    }

    private int uiAddButton(String title, int x, int y, int width, int height, int style) {
        Button button = new Button(this);
        button.setText(title);
        button.setTextSize(16);
        if (style == 1) {
            button.setTextColor(Color.WHITE);
            button.setBackgroundTintList(ColorStateList.valueOf(Color.rgb(0, 125, 214)));
        } else {
            button.setTextColor(Color.rgb(0, 92, 164));
            button.setBackgroundTintList(ColorStateList.valueOf(Color.rgb(220, 236, 249)));
        }
        final int controlId = storeControl(button, x, y, width, height);
        button.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                nativeControlActivated(controlId);
            }
        });
        return controlId;
    }

    private void uiSetControlText(int controlId, String text) {
        View control = controls.get(controlId);
        if (control instanceof TextView) {
            ((TextView) control).setText(text);
        }
    }

    private String uiGetControlText(int controlId) {
        View control = controls.get(controlId);
        return control instanceof TextView ? ((TextView) control).getText().toString() : "";
    }

    private void uiShowWindow() {
        content.setVisibility(View.VISIBLE);
    }

    private int dp(float value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private final class LogicalLayout extends ViewGroup {
        private final int logicalWidth;
        private final int logicalHeight;

        private LogicalLayout(int width, int height) {
            super(MainActivity.this);
            logicalWidth = width;
            logicalHeight = height;
        }

        @Override
        protected void onMeasure(int widthMeasureSpec, int heightMeasureSpec) {
            int measuredWidth = MeasureSpec.getSize(widthMeasureSpec);
            int measuredHeight = MeasureSpec.getSize(heightMeasureSpec);
            float scaleX = (float) measuredWidth / logicalWidth;
            float scaleY = (float) measuredHeight / logicalHeight;
            for (int i = 0; i < getChildCount(); i++) {
                LayoutParams params = (LayoutParams) getChildAt(i).getLayoutParams();
                getChildAt(i).measure(
                        MeasureSpec.makeMeasureSpec(Math.round(params.logicalWidth * scaleX), MeasureSpec.EXACTLY),
                        MeasureSpec.makeMeasureSpec(Math.round(params.logicalHeight * scaleY), MeasureSpec.EXACTLY));
            }
            setMeasuredDimension(measuredWidth, measuredHeight);
        }

        @Override
        protected void onLayout(boolean changed, int left, int top, int right, int bottom) {
            float scaleX = (float) (right - left) / logicalWidth;
            float scaleY = (float) (bottom - top) / logicalHeight;
            for (int i = 0; i < getChildCount(); i++) {
                View child = getChildAt(i);
                LayoutParams params = (LayoutParams) child.getLayoutParams();
                int childLeft = Math.round(params.logicalX * scaleX);
                int childTop = Math.round(params.logicalY * scaleY);
                child.layout(
                        childLeft,
                        childTop,
                        childLeft + child.getMeasuredWidth(),
                        childTop + child.getMeasuredHeight());
            }
        }

        private final class LayoutParams extends ViewGroup.LayoutParams {
            private final int logicalX;
            private final int logicalY;
            private final int logicalWidth;
            private final int logicalHeight;

            private LayoutParams(int x, int y, int width, int height) {
                super(width, height);
                logicalX = x;
                logicalY = y;
                logicalWidth = width;
                logicalHeight = height;
            }
        }
    }
}
