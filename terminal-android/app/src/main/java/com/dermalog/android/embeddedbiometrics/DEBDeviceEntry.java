package com.dermalog.android.embeddedbiometrics;
public class DEBDeviceEntry {
  public static final int TYPE_ZF1=1; private final int id,type; private final String serial;
  public DEBDeviceEntry(int id,int type,String serial){this.id=id;this.type=type;this.serial=serial;}
  public int getID(){return id;} public int getType(){return type;} public String getSerial(){return serial;}
}
