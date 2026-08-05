from PIL import Image

def make_white_transparent(image_path):
    img = Image.open(image_path)
    img = img.convert("RGBA")
    datas = img.getdata()

    newData = []
    # threshold for considering a pixel "white"
    threshold = 240
    
    for item in datas:
        # Check if RGB values are above the threshold (close to white)
        if item[0] > threshold and item[1] > threshold and item[2] > threshold:
            # Change to fully transparent
            newData.append((255, 255, 255, 0))
        else:
            newData.append(item)

    img.putdata(newData)
    img.save(image_path, "PNG")

if __name__ == "__main__":
    make_white_transparent("public/images/ns-logo.png")
    print("Logo updated: white background removed.")
