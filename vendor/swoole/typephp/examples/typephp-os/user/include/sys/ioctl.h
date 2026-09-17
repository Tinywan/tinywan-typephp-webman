#ifndef TYPEPHP_OS_USER_SYS_IOCTL_H
#define TYPEPHP_OS_USER_SYS_IOCTL_H

/* Linux-compatible terminal request values and winsize layout. */
struct winsize {
    unsigned short ws_row;
    unsigned short ws_col;
    unsigned short ws_xpixel;
    unsigned short ws_ypixel;
};

#define TIOCGWINSZ 0x5413UL
#define TIOCSWINSZ 0x5414UL
#define TIOCNOTTY 0x5422UL

int ioctl(int fd, unsigned long request, ...);

#endif
