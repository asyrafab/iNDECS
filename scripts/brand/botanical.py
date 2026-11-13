"""Authored botanical linework for L; geometry uses the icon's 120-unit grid."""
import math


def botanical(compact):
    def path(d, **attrs):
        attrs = ' '.join(f'{key.replace("_", "-")}="{value}"' for key,value in attrs.items())
        return f'<path d="{d}" {attrs}/>'

    def point(t, a, b, c, d):
        s=1-t
        return (s**3*a[0]+3*s*s*t*b[0]+3*s*t*t*c[0]+t**3*d[0],
                s**3*a[1]+3*s*s*t*b[1]+3*s*t*t*c[1]+t**3*d[1])

    def xy(p): return f'{p[0]:.3f} {p[1]:.3f}'

    def blend(a,b,t): return (a[0]*(1-t)+b[0]*t,a[1]*(1-t)+b[1]*t)

    def entire_leaf():
        # All vein ends meet an evaluated point on the leaf boundary.
        tip,base=(0,-30),(0,26)
        left=(tip,(-26,-18),(-23,13),base)
        right=(tip,(21,-15),(23,12),base)
        mid=(tip,(-2,-10),(3,10),base)
        result=path('M0-30C-26-18-23 13 0 26C23 12 21-15 0-30Z'
                    'M0-30C-2-10 3 10 0 26M0 26Q0 31-2 35')
        count=5 if compact else 10
        for side in [left,right]:
            for i in range(1,count+1):
                t=.06+i*.85/(count+1)
                edge=point(t,*side)
                root=point(min(t+.14,.99),*mid)
                c1=blend(root,edge,.33)
                c2=blend(root,edge,.72)
                c1=(c1[0],c1[1]-1.6)
                c2=(c2[0],c2[1]+.5)
                result+=path(f'M{xy(root)}C{xy(c1)} {xy(c2)} {xy(edge)}')
                if not compact and i%2==0:
                    # Smaller branches follow the lamina toward the margin.
                    start=point(.54,root,c1,c2,edge)
                    end=point(max(t-.05,.01),*side)
                    control=blend(start,end,.55)
                    result+=path(f'M{xy(start)}Q{xy(control)} {xy(end)}',opacity='.55')
        return result

    def split_leaf():
        # Broad rounded lobes separated by narrow natural sinuses.
        result=path('M0 24C-8 27-18 19-21 11C-24 6-24-2-22-4'
                    'C-20-4-11 3-10 1C-9-1-20-10-19-12'
                    'C-18-20-11-25-8-25C-7-24-3-13-2-14'
                    'C-1-16-4-27 0-30C8-29 16-22 19-16'
                    'C20-13 11-8 10-6C9-4 21-10 23-7'
                    'C26 0 25 7 22 10C20 11 9 10 9 12'
                    'C9 14 19 15 19 18C16 23 6 28 0 24Z'
                    'M0 32C-1 20-3-7 0-30')
        result+=path('M-1 20Q-10 20-18 14M-2 10Q-12 8-21 1'
                     'M-2 0Q-13-7-16-15M-1-12Q-8-20-9-24'
                     'M-1 19Q7 22 15 20M-2 8Q12 8 21 4'
                     'M-2-3Q12-6 17-17M-1-14Q6-21 7-26')
        if not compact:
            result+=path('M-7-2C-10-5-12-8-11-9C-9-10-6-5-7-2Z'
                         'M6 3C8 0 14-3 15-1C15 1 9 5 6 3Z'
                         'M-1-18C-3-21-3-24-1-24C1-24 1-20-1-18Z'
                         'M-5 15C-10 13-13 10-12 9C-10 8-5 12-5 15Z')
            result+=path('M-9 20Q-13 18-14 15M-10 8Q-15 6-17 3'
                         'M10 8Q16 6 18 2M7 21Q13 20 15 17'
                         'M-4 21Q-11 21-15 17M-2 17Q-11 17-18 12'
                         'M-3 8Q-13 4-19-1M-4-2Q-12-9-15-16'
                         'M-2-11Q-8-18-10-22M1-10Q9-18 12-23'
                         'M2-2Q13-4 20-7M1 7Q12 6 21 1'
                         'M1 18Q9 19 16 17',opacity='.65')
        return result

    def flower(cx,cy,scale,rotation):
        pieces=[]
        for angle in range(0,360,72):
            petal=path('M-1.1 1.4C-4.8 2-6.1 5.8-4 8.1'
                       'C-2.8 10.2-.9 9.7 0 8.7C1.2 10 3.6 9.9 4.6 7.7'
                       'C6 4.6 3.8 2 1.1 1.4Z')
            if not compact:
                petal+=path('M0 2.4Q-1.8 4.5-1.6 7.4M.5 2.6Q2.2 4.8 2.1 7',opacity='.65')
            pieces.append(f'<g transform="rotate({angle}) scale(.76 1)">{petal}</g>')
        pieces.append('<circle r="1.7"/>')
        pieces.append(path('M.2-.3Q2-2.6 3.4-3.3'))
        pieces.append('<circle cx="3.5" cy="-3.4" r=".7"/>')
        return f'<g transform="translate({cx} {cy}) rotate({rotation}) scale({scale})">{"".join(pieces)}</g>'

    result=[]
    # Same quadrant arrangement as the approved concept, with complete smaller
    # silhouettes and detailed veins rather than oversized disconnected lobes.
    for kind,x,y,angle,scale in [('split',27,31,-41,.77),
                                ('entire',94,28,39,.73),
                                ('entire',25,78,-42,.86),
                                ('split',94,69,44,.79),
                                ('entire',93,106,61,.51)]:
        leaf=split_leaf() if kind=='split' else entire_leaf()
        result.append(f'<g transform="translate({x} {y}) rotate({angle}) scale({scale})">{leaf}</g>')
    # Curved stems and smaller foliage carry the linework through the gaps.
    result.append(path('M10 111C29 101 31 88 34 69M110 109Q93 95 89 89'
                       'M39 113Q47 104 51 99M49 30Q56 26 60 19'
                       'M76.79 46.93Q67 58 47 70M78 91Q74 100 68 106'
                       'M9 109Q17 102 22 104C17 108 13 110 9 109Z'
                       'M13 113Q21 109 29 111C23 114 18 115 13 113Z'
                       'M102 99Q105 92 111 90C112 96 107 99 102 99Z'))
    result.append(flower(64,20,.82,10))
    result.append(flower(54,104,.86,24))
    if not compact:
        result.append(flower(74,110,.44,-9))
    return ''.join(result)
