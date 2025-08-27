import { ArrowRight, Clock, Globe, MapPin, Shield, Star, TrendingUp, Users, Zap } from "lucide-react"
import Image from "next/image"
import Link from "next/link"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"

export default function HomePage() {
  return (
    <div className="min-h-screen bg-gray-200">
      {/* Navigation */}
      <nav className="border-b border-primary/20 bg-primary sticky top-0 z-50 shadow-lg">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center">
              <div className="flex-shrink-0 flex items-center">
                <Image
                  src="/images/zimple-logo.png"
                  alt="Zimple Travel Group"
                  width={160}
                  height={40}
                  className="h-10 w-auto brightness-0 invert"
                />
              </div>
            </div>
            <div className="hidden md:block">
              <div className="ml-10 flex items-baseline space-x-4">
                <Link
                  href="#services"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Services
                </Link>
                <Link
                  href="/partners"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Partners
                </Link>
                <Link
                  href="#benefits"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Benefits
                </Link>
                <Link
                  href="#contact"
                  className="text-white hover:text-yellow-200 px-3 py-2 text-sm font-medium transition-colors"
                >
                  Contact
                </Link>
              </div>
            </div>
            <div className="flex items-center space-x-4">
              <Button
                variant="outline"
                size="sm"
                className="border-white text-white hover:bg-white hover:text-primary bg-transparent"
              >
                Login
              </Button>
              <Button size="sm" className="bg-white text-primary hover:bg-gray-100">
                Join Network
              </Button>
            </div>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="relative bg-white py-8">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-2 gap-8 items-center">
            <div>
              <Badge className="mb-3 bg-primary/10 text-primary border-primary/20 text-sm">
                Global Car Rental Network
              </Badge>
              <h1 className="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-3 leading-tight">
                Partner with the <span className="text-primary">Leading</span> Car Rental Platform
              </h1>
              <p className="text-lg text-gray-600 mb-5 leading-relaxed max-w-xl">
                Join Zimple Travel Group and unlock access to trusted car rental brands across 100+ countries. Earn
                competitive commissions with 24/7 real-time booking capabilities.
              </p>
              <div className="flex flex-col sm:flex-row gap-3 mb-6">
                <Button size="lg" className="bg-primary hover:bg-primary/90 text-base">
                  Become a Partner
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
                <Button variant="outline" size="lg" className="text-base bg-transparent">
                  View Demo
                </Button>
              </div>
              <div className="grid grid-cols-3 gap-4">
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary">100+</div>
                  <div className="text-sm text-gray-600">Countries</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary">5000+</div>
                  <div className="text-sm text-gray-600">Cities</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold text-primary">24/7</div>
                  <div className="text-sm text-gray-600">Support</div>
                </div>
              </div>
            </div>
            <div className="relative">
              <div className="relative z-10">
                <Image
                  src="/placeholder.svg?height=500&width=700"
                  alt="Car rental dashboard"
                  width={700}
                  height={500}
                  className="rounded-xl shadow-2xl"
                />
              </div>
              <div className="absolute -top-4 -right-4 w-64 h-64 bg-yellow-200 rounded-full mix-blend-multiply filter blur-xl opacity-60 animate-pulse"></div>
              <div className="absolute -bottom-8 -left-4 w-64 h-64 bg-primary/20 rounded-full mix-blend-multiply filter blur-xl opacity-60 animate-pulse"></div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="services" className="py-8 bg-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Why Choose Zimple Travel Group?</h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              We provide everything you need to succeed in the car rental industry with our comprehensive platform and
              support system.
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Globe className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Global Network</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Access to car rental services in over 100 countries and thousands of cities worldwide.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <TrendingUp className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Competitive Commission</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Earn attractive commissions with our transparent and competitive rate structure.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Clock className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">24/7 Availability</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Round-the-clock support and real-time booking processing for your customers.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Zap className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Real-Time Processing</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Instant reservation confirmations with direct connections to car rental providers.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Shield className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Trusted Brands</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Partner with established and reliable car rental companies across the globe.
                </CardDescription>
              </CardHeader>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 p-5 bg-white hover:bg-gray-50 group">
              <CardHeader className="pb-3">
                <div className="w-12 h-12 bg-gradient-to-br from-primary to-primary/80 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform duration-300">
                  <Users className="h-6 w-6 text-white" />
                </div>
                <CardTitle className="text-xl font-semibold text-gray-900 mb-1">Dedicated Support</CardTitle>
                <CardDescription className="text-gray-600 leading-relaxed">
                  Personal account management and technical support to help you succeed.
                </CardDescription>
              </CardHeader>
            </Card>
          </div>
        </div>
      </section>

      {/* Partners Section */}
      <section id="partners" className="py-8 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Trusted by Leading Brands</h2>
            <p className="text-lg text-gray-600">
              We work with trusted car rental partners to provide reliable service worldwide
            </p>
          </div>
          <div className="flex justify-center items-center gap-8 md:gap-12 lg:gap-16">
            <div className="flex justify-center">
              <Image
                src="/images/logo-enterprise.avif"
                alt="Enterprise Rent-A-Car"
                width={140}
                height={70}
                className="h-16 w-auto object-contain opacity-70 hover:opacity-100 transition-opacity"
              />
            </div>
            <div className="flex justify-center">
              <Image
                src="/images/logo-alamo.png"
                alt="Alamo Rent A Car"
                width={140}
                height={70}
                className="h-16 w-auto object-contain opacity-70 hover:opacity-100 transition-opacity"
              />
            </div>
            <div className="flex justify-center">
              <Image
                src="/images/logo-national.gif"
                alt="National Car Rental"
                width={140}
                height={70}
                className="h-16 w-auto object-contain opacity-70 hover:opacity-100 transition-opacity"
              />
            </div>
          </div>
        </div>
      </section>

      {/* Blog Section */}
      <section className="py-8 bg-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-6">
            <h2 className="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Latest Insights</h2>
            <p className="text-lg text-gray-600 max-w-2xl mx-auto">
              Stay updated with the latest trends, tips, and insights from the car rental industry
            </p>
          </div>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5 mb-6">
            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
              <div className="relative h-40 overflow-hidden">
                <Image
                  src="/placeholder.svg?height=200&width=400"
                  alt="Car rental market trends"
                  width={400}
                  height={200}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
                <div className="absolute top-3 left-3">
                  <Badge className="bg-primary text-white text-xs">Industry Trends</Badge>
                </div>
              </div>
              <CardContent className="p-4">
                <div className="flex items-center text-xs text-gray-500 mb-2">
                  <span>March 15, 2024</span>
                  <span className="mx-2">•</span>
                  <span>5 min read</span>
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                  The Future of Car Rental: Digital Transformation Trends
                </CardTitle>
                <CardDescription className="text-sm text-gray-600 mb-3 line-clamp-2">
                  Explore how digital innovation is reshaping the car rental industry and what it means for travel
                  partners worldwide.
                </CardDescription>
                <Link
                  href="#"
                  className="inline-flex items-center text-primary hover:text-primary/80 font-medium text-sm"
                >
                  Read More
                  <ArrowRight className="ml-1 h-3 w-3" />
                </Link>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
              <div className="relative h-40 overflow-hidden">
                <Image
                  src="/placeholder.svg?height=200&width=400"
                  alt="Partnership success stories"
                  width={400}
                  height={200}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
                <div className="absolute top-3 left-3">
                  <Badge className="bg-yellow-500 text-white text-xs">Success Stories</Badge>
                </div>
              </div>
              <CardContent className="p-4">
                <div className="flex items-center text-xs text-gray-500 mb-2">
                  <span>March 10, 2024</span>
                  <span className="mx-2">•</span>
                  <span>7 min read</span>
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                  How Our Partners Increased Revenue by 40% in 2023
                </CardTitle>
                <CardDescription className="text-sm text-gray-600 mb-3 line-clamp-2">
                  Discover the strategies and tools that helped our top-performing partners achieve remarkable growth
                  last year.
                </CardDescription>
                <Link
                  href="#"
                  className="inline-flex items-center text-primary hover:text-primary/80 font-medium text-sm"
                >
                  Read More
                  <ArrowRight className="ml-1 h-3 w-3" />
                </Link>
              </CardContent>
            </Card>

            <Card className="border-0 shadow-lg hover:shadow-xl transition-all duration-300 bg-white overflow-hidden group">
              <div className="relative h-40 overflow-hidden">
                <Image
                  src="/placeholder.svg?height=200&width=400"
                  alt="Travel industry insights"
                  width={400}
                  height={200}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
                <div className="absolute top-3 left-3">
                  <Badge className="bg-blue-500 text-white text-xs">Travel Tips</Badge>
                </div>
              </div>
              <CardContent className="p-4">
                <div className="flex items-center text-xs text-gray-500 mb-2">
                  <span>March 5, 2024</span>
                  <span className="mx-2">•</span>
                  <span>4 min read</span>
                </div>
                <CardTitle className="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                  Global Travel Recovery: What the Data Shows
                </CardTitle>
                <CardDescription className="text-sm text-gray-600 mb-3 line-clamp-2">
                  An in-depth analysis of travel recovery patterns and emerging opportunities in key markets worldwide.
                </CardDescription>
                <Link
                  href="#"
                  className="inline-flex items-center text-primary hover:text-primary/80 font-medium text-sm"
                >
                  Read More
                  <ArrowRight className="ml-1 h-3 w-3" />
                </Link>
              </CardContent>
            </Card>
          </div>
          <div className="text-center">
            <Button
              variant="outline"
              size="lg"
              className="bg-white border-primary text-primary hover:bg-primary hover:text-white"
            >
              View All Articles
              <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
          </div>
        </div>
      </section>

      {/* Benefits Section */}
      <section id="benefits" className="py-8 bg-gradient-to-r from-primary to-primary/80">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-2 gap-6 items-center">
            <div>
              <h2 className="text-2xl lg:text-3xl font-bold text-white mb-3">Start Earning Today</h2>
              <p className="text-lg text-white/90 mb-5">
                Join thousands of partners who are already benefiting from our comprehensive car rental platform.
              </p>
              <div className="space-y-2">
                <div className="flex items-center text-white">
                  <Star className="h-4 w-4 mr-3 text-yellow-300" />
                  <span className="text-base">Up to 15% commission on every booking</span>
                </div>
                <div className="flex items-center text-white">
                  <Star className="h-4 w-4 mr-3 text-yellow-300" />
                  <span className="text-base">Free integration and setup support</span>
                </div>
                <div className="flex items-center text-white">
                  <Star className="h-4 w-4 mr-3 text-yellow-300" />
                  <span className="text-base">Marketing materials and training provided</span>
                </div>
                <div className="flex items-center text-white">
                  <Star className="h-4 w-4 mr-3 text-yellow-300" />
                  <span className="text-base">Monthly performance reports and analytics</span>
                </div>
              </div>
              <div className="mt-5">
                <Button size="lg" variant="secondary" className="bg-white text-primary hover:bg-gray-100">
                  Get Started Now
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
              </div>
            </div>
            <div className="relative">
              <Card className="bg-white/10 backdrop-blur border-white/20 p-5">
                <CardHeader className="pb-3">
                  <CardTitle className="text-white text-lg">Partnership Benefits</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="flex justify-between items-center text-white">
                    <span className="text-sm">Average Monthly Commission</span>
                    <span className="text-xl font-bold text-yellow-300">$5,000+</span>
                  </div>
                  <div className="flex justify-between items-center text-white">
                    <span className="text-sm">Setup Time</span>
                    <span className="text-xl font-bold text-yellow-300">{"< 24h"}</span>
                  </div>
                  <div className="flex justify-between items-center text-white">
                    <span className="text-sm">Support Response</span>
                    <span className="text-xl font-bold text-yellow-300">{"< 1h"}</span>
                  </div>
                  <div className="flex justify-between items-center text-white">
                    <span className="text-sm">Partner Satisfaction</span>
                    <span className="text-xl font-bold text-yellow-300">98%</span>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section id="contact" className="py-8 bg-gray-900">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-2xl lg:text-3xl font-bold text-white mb-3">Ready to Join Our Network?</h2>
          <p className="text-lg text-gray-300 mb-5">
            Take the first step towards expanding your business with our global car rental platform.
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center">
            <Button size="lg" className="bg-primary hover:bg-primary/90">
              <MapPin className="mr-2 h-4 w-4" />
              Become a Partner
            </Button>
            <Button size="lg" variant="outline" className="border-gray-600 text-white hover:bg-gray-800 bg-transparent">
              Schedule a Demo
            </Button>
          </div>
          <div className="mt-5 text-gray-400">
            <p className="text-sm">
              Questions? Contact us at{" "}
              <a href="mailto:partners@zimpletravel.com" className="text-primary hover:underline">
                partners@zimpletravel.com
              </a>
            </p>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-gray-800 text-white py-6">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid md:grid-cols-4 gap-6">
            <div>
              <div className="flex items-center mb-3">
                <Image
                  src="/images/zimple-logo.png"
                  alt="Zimple Travel Group"
                  width={140}
                  height={35}
                  className="h-8 w-auto brightness-0 invert"
                />
              </div>
              <p className="text-gray-400 text-sm">Your trusted partner for global car rental solutions.</p>
            </div>
            <div>
              <h3 className="font-semibold mb-3 text-base">Services</h3>
              <ul className="space-y-1 text-gray-400 text-sm">
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Car Rental Booking
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Partner Network
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    API Integration
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    24/7 Support
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold mb-3 text-base">Company</h3>
              <ul className="space-y-1 text-gray-400 text-sm">
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    About Us
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Careers
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Press
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Contact
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold mb-3 text-base">Legal</h3>
              <ul className="space-y-1 text-gray-400 text-sm">
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Privacy Policy
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Terms of Service
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    Cookie Policy
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-white transition-colors">
                    GDPR
                  </Link>
                </li>
              </ul>
            </div>
          </div>
          <div className="border-t border-gray-700 mt-4 pt-4 text-center text-gray-400">
            <p className="text-sm">&copy; 2024 Zimple Travel Group. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  )
}
